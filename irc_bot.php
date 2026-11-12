<?php

/**
 * извлечение из строки содержимого между первой пары операторных скобок
 *
 * $s = "qewqe <b>fgh</b> lkkj <b>hgbv</b> kjlk";
 * print_r between($s, "<b>", "</b>"); // ==> fgh
 * @package abc
 * @autor KonmeB
 * @param string исходная строка
 * @param string открывающая скобка
 * @param string закрывающая скобка
 * @return string содержимое между первой парой скобок
 */
function between($s,$open,$close="")
    {
    if (!strlen($open) && !strlen($close)) return $s;
    $k=(strlen($open))?strpos($s,$open):0;
    if ($k===false) return false;
    $s=substr($s,$k+strlen($open));
    if (!strlen($close)) return $s;
    $k=strpos($s,$close);
    if ($k===false) return false;
    if ($k===0) return "";
    return substr($s,0,$k);
    }

/**
 * отправка эхо-ответа IRC-серверу
 *
 * @package irc
 * @autor KonmeB
 * @param string исходная строка
 * @return void
 */
function pong($line)
    {
    $otvet="";
    if (substr($line,0,6)=="PING :")
      $otvet.="PONG :".substr($line,6);
    return $otvet;
    }

/**
 * нахождение суммы двух и более чисел (тестовая задача)
 *
 * :HexDroidUser!hexdroid@80.253.13.209 PRIVMSG #hyperchat :2+3=
 * @package irc
 * @autor KonmeB
 * @param string исходная строка
 * @return string ответ с найденной суммой
 */
function summa($line)
    {
    $otvet="";
    if (strpos($line, "+") && strpos($line, "="))
      {
      $chan=between($line,"#"," :");
      $calc=between($line," :","=");
      $ccc=explode("+",$calc);
      $xxx=array();
      foreach ($ccc as $c)
        {
        $c=trim($c);
        if (is_numeric($c))
          $xxx[]=$c;
        }
      if (count($xxx)>1)
        {
        $z=0;
        foreach ($xxx as $x)
          {
          $x=trim($x);
          $z=bcadd($z,$x);
          }
        $otvet.="PRIVMSG #$chan :".implode(" + ", $xxx)." = $z\r\n";
        }
      }
    return $otvet;
    }

/**
 * вызов пользовательской функции в качестве обработчика
 *
 * @package irc
 * @autor KonmeB
 * @param string исходная строка
 * @param string вызываемая функция
 * @return string ответное сообщение с полученным результатом вызова
 */
function otvet($line, $fun)
    {
    $otvet="";
    $chan=between($line,"#"," :");
    $arr=explode(" :",$line,2);
    $req1=(count($arr)==2)?$arr[1]:"";
    if (substr(strtolower($req1),0,strlen($fun)+2)=="*".strtolower($fun)." ")
      {
      $req=trim(substr($req1,strlen($fun)+2));//str_replace("*$fun ","",$req1);
      if (is_callable($fun))
        {
        $res=call_user_func($fun, $req);
        if (strlen($res))
          $otvet.="PRIVMSG #$chan :".$res."\r\n";
        }
      }
    return $otvet;
    }

/**
 * выдаёт сигнатуру функции (с переметрами и типом результата)
 *
 * @package php
 * @autor KonmeB
 * @param string проверяемая функция
 * @return string информация о функции
 */
function reflect($fun, $wrap=false)
    {
    $ref=new ReflectionFunction($fun);
    $r_type = "fake";//"???";
    $return_type=$ref->getReturnType();
    if (!is_null($return_type))
      $r_type = $return_type->__toString();
    if (!strpos($ref->__toString(), "- Return ["))         //!! PATCH !!
      {                                                    //!! PATCH !!
      $r_type="resource|false";                            //!! PATCH !!
      if (substr($fun,0,4)=="set_") $r_type="?callable";   //!! PATCH !!
      if ((substr($fun,0,15)=="stream_context_")           //!! PATCH !!
       ||(substr($fun,0,14)=="stream_filter_")) $r_type="resource"; //!!
      }
    $parr=array();
    foreach ($ref->getParameters() as $k => $param)
      {
      $t = $param->getType();
      $n = ($param->isPassedByReference())?"&":"";
      if ($param->isVariadic())
        $n.="...";
      $n.="$".$param->name;
      $tmp=explode(" = ",$param->__toString());    //!!костыль
      $v=(count($tmp)==2)?substr($tmp[1],0,-2):""; //!!костыль
      $para=$n;
      if (!is_null($t))
        $para=$t." ".$para;
      if (strlen($v))
        $para.=" = ".$v;
      $parr[]=$para;
      }
    $cr = ($wrap && (count($parr)>1))?"\r\n":"";
    $indent = ($wrap && (count($parr)>1))?"  ":"";
    $delim = ($wrap && (count($parr)>1))?$cr.$indent:" ";
    $params=implode(",".$delim, $parr);
    return $fun."("
     .$cr.$indent.$params.$cr
     ."): ".$r_type."\r\n";
    }

/**
 * вызов помощи по запрошенной функции
 *
 * @package irc
 * @autor KonmeB
 * @param string вызываемая функция
 * @return string помощь по функции (параметры и тип результата)
 */
function help($fun)
    {
    if (is_callable($fun))
      return reflect($fun);
    return false;
    }

/**
 * обработка входящего IRC-сообщения (упрощённо)
 *
 * @package irc
 * @autor KonmeB
 * @param array очередь необработанных строк
 * @return void
 */
function irc($lines)
    {
    foreach ($lines as $line)
        {
        if (str_starts_with($line, 'PING'))
          {
          fwrite($sock, "PONG :irc\r\n");
          continue;
          }
        if (preg_match('/^:(\S+) PRIVMSG (\S+) :(.+)$/i', $line, $m))
          {
          [$nick, $chan, $text] = [$m[1], $m[2], trim($m[3])];
          if ($text[0] === '*')
            {
            [$cmd, $args] = array_pad(explode(' ', $text, 2), 2, '');
            $bot->dispatch($nick, $chan, strtolower($cmd), $args);
            }
          }
        }
    }

/**
 * автопроверка ответа и начисление балла
 *
 * @package test
 * @autor KonmeB
 * @param object вопрос из теста
 * @param string ответ пользователя
 * @return bool результат проверки
 */
function check($q, $answer): bool
    {
    return match ($q->type)
      {
      'single' => trim($answer) === (string)$q->correct,
      'number' => abs((float)$answer - (float)$q->correct) < 0.001,
      'text'   => mb_strtolower(trim($answer)) === mb_strtolower($q->correct),
      'multi'  => normalizeSet($answer) === normalizeSet($q->correct),
      default  => false
      };
    }

// Инициализация
// Параметры IRC сервера
$irc_server["ip"]="185.26.183.143";//'213.236.208.178';
//$irc_server["name"]="irc.opera.com";
$irc_server["port"]=6667;

$nick="anonymous";
$name="noname";
$info="NULL";

$channels[]="#hyperchat";

$fp = fsockopen($irc_server["ip"], $irc_server["port"], $errno, $errstr, 30)
 or die("ERROR $errno: $errstr");

// echo fgets($fp); //:irc.opera.com NOTICE AUTH :*** Looking up your hostname...
// echo fgets($fp); //:irc.opera.com NOTICE AUTH :*** Found your hostname

$otvet="NICK $nick\r\n"
 ."USER $name 8 * :$info\r\n";
fwrite($fp, $otvet);

// fwrite($fp,"NICK $nick\r\n"); //fwrite($fp,"NICK botinok\r\n");
// fwrite($fp,"USER $name 8 * :$info\r\n");// "USER rotuarnaya 8 * :qqq2\r\n"

//стартовый цикл бота
$otvet="";
while (!feof($fp))
  {
  $line=fgets($fp);
  echo $line;
  $otvet.=pong($line);
  if (strlen($otvet))
    {
    foreach ($channels as $channel)
      $otvet.="JOIN $channel\r\n";
    break;
    }
  }

//основной цикл бота
while (!feof($fp))
  {
  if (strlen($otvet))
    {
    fwrite($fp, $otvet);
    $otvet="";
    }
  $line=fgets($fp);
  echo $line;
  $otvet.=pong($line); // ответ на PING
  $otvet.=summa($line); // считает сумму 1+2+3=
  // тут можно добавлять свои функции-обработчики
  $otvet.=otvet($line, "help"); // показывает справку по функции
  $otvet.=otvet($line, "strtoupper"); // переводит в верхний регистр
  }

fclose($fp);
