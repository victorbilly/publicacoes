<?php

  /**
   * Class to minning from a big data in memory
   */
  const DEBUG = false;

  function DUMP($str){
      if(DEBUG) echo $str . '<br/>';
  }



  class Process
  {
      private $vara      = null;
      private $filters   = array();
      private $processed = array();
      private $another   = array();

      function __construct($filters) {
          $this->filters = $filters;
      }

      public function setVara($value) {
          $this->vara = $value;
      }

      //main method to process filters in data memory array
      public function run($data) {
          $N        = 200; //explanation next
          $cont     = 0;

          echo 'Processando ' . count($data) . ' registros ... <br/>';
          $vara = $this->vara; //just for inline purpose
          foreach ($data as $row):
              // echo substr($row['cont'], 15, 20);
              $str  = $row['ra_conteudo']; //array with just one database column

              $juiz = $this->getJuizFromString($str);

              //--getting Processual number information-------
              $pos = strpos($str, 'Processo'); //using this slug ('Processo') for reducing searching area

              //exploding N=200 chars for '-' slug from position at the Rord Processo occur, to capture process number and types of processual information
              $arr = explode('-' , substr($str, $pos, $N)); //200 is a secure range for reduce string length by optimazation purpose

              $processo=null;
              if(count($arr)>=2){
                  $processo  = substr($arr[0], 9) . '-'; //removing word Processo , 9 chars, taking first part of the number
                  $processo .= substr($arr[1], 0, 17); //finding process number, with 17 digits

                  unset($arr[0]); //word processso
                  unset($arr[1]); //processso number
              }
              //--end of processual information

              // DUMP('Processo: ' . $processo);
              // DUMP('Juiz: ' . $juiz);

              //checking initial filter for VARA information
              $pos = strpos($str, $vara);
              if($pos){ //found first query
                  $cont++;

                  $str_flag = implode($arr); //turning it a word again

                  //checking publications
                  DUMP($str_flag);

                  $flag=false;
                  //checkando os tipos de publicações
                  foreach ($this->filters as $key => $value) {
                      DUMP('searching for: ' . $key);
                      $pos = strpos($str_flag, $key);
                      if($pos){
                          DUMP('found at position: ' . $pos . '<br>' . substr($str, 350, 100) .'</br>');
                          $flag=true;
                          $this->processed[$value][] = array('processo'=>$processo,
                                                             'juiz'    =>$juiz,
                                                             'conteudo'=>$row[0]); //$row[0] -> just on content filed in column database
                          break; //skipping for reduces clock
                      }
                  }
                  //if does not match any filter, put in another array option
                  if(!$flag)
                      $this->processed['outros'][] = array('processo'=>$processo,
                                                           'juiz'    =>$juiz,
                                                           'conteudo'=>$row[0]); //$row[0] -> just on content filed in column database
              }else{ //does not match VARA information
                  $this->another[] =  array('processo'=>$processo,
                                            'juiz'    =>$juiz,
                                            'conteudo'=>$str); //$row[0] -> just on content filed in column database
              }
          endforeach;

          echo 'Encontrou ' . $cont . ' ocorrências na VARA : ' . $vara . '<br/>';

          //--write output json files
          $this->writeOutputByProcess();
      }

      //--filtering JUIZ informaton from a string parameter
      public function getJuizFromString($str){
          $pos_j = strpos($str, 'JUIZ(A)');
          if($pos_j){
              $flag_break = strpos($str, 'ESCRIVÃ(O)');
              //removing prefix
              $k = strlen('JUIZ(A) DE DIREITO');
              return substr($str, $pos_j+$k, ($flag_break-$pos_j)-$k );
          }else{
              return null; //not found
          }
      }


      //function to output data in json files
      public function writeOutputByProcess() {

          echo "<br> Gerando arquivos de saída...";

          //-----
          //5.1 - Arquivos separados por cada tipo de ação,
          //tendo como critério de ordenação o nome do juiz responsável;

          //sorting by JUIZ (name)
          foreach ($this->processed as $key => $value) {
              usort($this->processed[$key], array($this, "cmpSortByJuiz"));
          }

          //write in json files
          foreach($this->processed as $key => $value):
              $fp = fopen('files1/'.$key.'.json', 'w');
              fwrite($fp, json_encode($value, JSON_FORCE_OBJECT | JSON_UNESCAPED_UNICODE));
              fclose($fp);
          endforeach;
          //--end of 5.1

          //sorting by processual number
          foreach ($this->processed as $key => $value) {
              usort($this->processed[$key], array($this, "cmpSortByProcess"));
          }

          //------
          //5.2 - Arquivos separados por cada tipo de ação e Juiz responsável,
          //tendo como critério de ordenação o número do processo;
          foreach ($this->processed as $key => $value){
              $result = $this->arrayChunkByJuiz($this->processed[$key]);
              // foreach ($result as $key => $value)
              // usort($result[$key], array($this, "cmpSortByProcess")); //sorting by process
              $cont_juiz=0;
              foreach($result as $key_r => $value_r):
                $fp = fopen('files2/'.$key. '-juiz_' . ++$cont_juiz .'.json', 'w');
                fwrite($fp, json_encode($value_r, JSON_FORCE_OBJECT | JSON_UNESCAPED_UNICODE));
                fclose($fp);
              endforeach;
          }
          //--end of 5.2

          //---
          //5.3 - Arquivo contendo as publicações não pertencentes a 4a Vara da Família e Sucessões.
          $total_another = count($this->another);
          $step  = 10;
          $piece = round($total_another/$step);
          echo 'Outros processos: ' . $total_another . ' <br/>';
          $fp   = fopen('files3/big_another.json', 'wb');

          fwrite($fp, '[');
          for($i=0; $i<$step; $i++){
              $start = $i*$piece;
              $end   = ($start+$piece)-1; //for example 0 ... 999, 1000 to 1999

              $temp = array_slice($this->another, $start, $end);
              fwrite($fp, json_encode($temp, JSON_FORCE_OBJECT | JSON_UNESCAPED_UNICODE));
          }
          fwrite($fp, ']');
          fclose($fp);
          //--end of 5.3
      }

      //chunk the array by JUIZ information
      private function arrayChunkByJuiz($array){
          $process = array();
          $map    = array();

          $map[0] = $array[0]['juiz'];
          $num = count($array);
          for($i=1; $i<$num; $i++){
              if(!in_array($array[$i]['juiz'], $map)) $map[] = $array[$i]['juiz'];
          }
          // print_r($map);

          foreach($map as $key){
              $process[$key]=array(); //initialigz the array for results
          }
          // print_r($process); // die();
          for($i=0; $i<$num; $i++){
              $process[$array[$i]['juiz']][] = array('processo'=>$array[$i]['processo'],
                                                     'conteudo'=>$array[$i]['conteudo']);
          }

          //mapping...
          $result = array();
          foreach($map as $key){
              $result[] = array($key=>$process[$key]);
          }
          // print_r($result); die();
          return $result;
      }

      //comparing method for sorting array by 'Processo' key
      static function cmpSortByProcess($a, $b) {
          //casting process for numeric value, ex processual number: 0015050-92.2019.8.26.0554
          $pa    = explode('-', $a["processo"]);
          $pa[0] = (int) $pa[0]; //casting first number os processual
          $pa[1] = (int) str_replace('.', '', $pa[1]);

          //casting process for numeric value, ex processual number: 0015050-92.2019.8.26.0554
          $pb    = explode('-', $b["processo"]);
          $pb[0] = (int) $pb[0]; //casting first number os processual
          $pb[1] = (int) str_replace('.', '', $pb[1]);

          if ($pa[0] == $pb[0]) { //caso de processos na posição 0 iguais
              if($pa[1] == $pb[1]) return 0;
              else return ($pa[1] < $pb[1]) ? -1 : 1;
          }
          //return strcmp($a["processo"], $b["processo"]);
          return ($pa[0] < $pb[0]) ? -1 : 1;
      }

      //sorting by JUIZ (name) info
      static function cmpSortByJuiz($a, $b) {

          if ($a['juiz'] == $b['juiz']) {
              return 0;
          }
          return strcmp($a["juiz"], $b["juiz"]);
      }


  }
