<?php

  include_once("HTML2LaTeX.php");

  if ($argc != 2) {
    echo "Usage: php $argv[0] <input.html>", PHP_EOL;
    die(0);
    }

  $inputfile = $argv[1];

  $H = new HTML2LaTeX();
  $H->setLanguage("english");
  $H->setVerbosity(0);
  $H->createZip($inputfile);

?>
