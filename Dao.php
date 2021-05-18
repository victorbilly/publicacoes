<?php

    class DAO{
      //variables
      private $host     = '127.0.0.1';
      private $user     = 'billy';
      private $password = 'billy';
      private $database = 'publicacoes';
      private $con      = null;

      function __construct(){
          $dsn = 'pgsql:dbname='.$this->database.';host='.$this->host;
          $this->connect($dsn, $this->user, $this->password); //cnect in the constructor
      }

      //connect function
      public function connect($dsn, $user, $pass) {
          try {
              $this->con = new PDO($dsn, $user, $pass);
          } catch (\PDOException $e) {
              die($e);
          }
      }

      //recovering data from database
      public function getData($table, $limit=null) {
          $sql   = "select ra_conteudo from " . $table;
          if($limit) $sql .= ' limit '. $limit;

          $query   = $this->con->query($sql);
          $content = $query->fetchAll();

          $this->disconect();

          return $content; //returning data
      }

      //just disconnect function
      public function disconect(){
          $this->con=null;
      }
    }
