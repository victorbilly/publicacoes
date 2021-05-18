<?php
/**
1 - Carregar todas as publicações em memória;
2 - Identificar as publicações pertencentes a 4ª Vara da Família e Sucessões;
3 - Identificar as publicações referentes as ações de Alimentos, Divórcio, Investigação de Paternidade, Inventário, Outros;
3 - Cada documento deverá receber um atributo contendo o número do processo da publicação extraído do texto;
4 - Cada documento deverá receber um atributo contendo o nome do juiz responsável pelo processo extraído do texto;
5 - Como arquivo de saída, deverão ser criados dois conjuntos de arquivos json:
5.1 - Arquivos separados por cada tipo de ação, tendo como critério de ordenação o nome do juiz responsável;
5.2 - Arquivos separados por cada tipo de ação e Juiz responsável, tendo como critério de ordenação o número do processo;
5.3 - Arquivo contendo as publicações não pertencentes a 4a Vara da Família e Sucessões.
*/

  require './Dao.php';
  require './Process.php';

  const NUMBER_OF_LINES=100000;
  const TABLE = 'publicacoes_fila_2020_08_02';

  //retrieving data from database
  $dao  = new Dao();
  $data = $dao->getData(TABLE); //get all data

  const VARA = '4ª Vara da Família e Sucessões';
  //publication type of process filters
  $filters = array('Alimentos'   => 'alimentos',
                   'Divórcio'    => 'divorcio',
                   'Inventário'  => 'inventario',
                   'Investigação de Paternidade' => 'paternidade' );

  //proccessing filters
  $proc = new Process($filters);
  $proc->setVara(VARA);
  $proc->run($data);
