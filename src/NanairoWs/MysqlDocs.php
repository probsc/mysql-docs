<?php 

namespace NanairoWs;

use Aura\Cli\CliFactory;

/**
 * MySQLの構造をExcelで出力
 */
class MysqlDocs
{
    private $props = [];
    private static $keys = ['dsn', 'user', 'database', 'password', 'template', 'output'];
    
    public function __construct()
    {
        require __dir__.'/Configs.php';
        $this->output = 'output.xlsx';
        $this->template = __dir__.'/../template.xlsx';
    }
    
    
    /**
     * CLI で実行する
     */
    public function run()
    {
        if (PHP_SAPI != 'cli') {
            throw new Exception('CLIで起動してください。', MysqlDocs\RUN_ON_CLI);
        }
        
        date_default_timezone_set('Asia/Tokyo');
        
        $cli_factory = new CliFactory;
        $context = $cli_factory->newContext($GLOBALS);
        
        // 指定されるオプションを用意
        $getopt = $context->getopt([
            's::',
            'd::',
            'u::',
            'o:',
            't:',
            'p:'
        ]);
        
        $this->dsn = $getopt->get('-s', 'mysql:host=127.0.0.1');
        $this->database = $getopt->get('-d');
        $this->user = $getopt->get('-u');
        $this->template = $getopt->get('-t', $this->template);
        $this->output = $getopt->get('-o', $this->output);
        
        $password = $getopt->get('-p');
        
        $stdio = $cli_factory->newStdio();
        
        // Password
        if (empty($password)) {
            $stdio->outln('Password:');
            system('stty -echo');
            $this->password = $stdio->in();
            system('stty echo');
        } else {
            $this->password = $password;
        }
        
        try {
            $this->output();
        } catch (\Exception $e) {
            if (in_array($e->getcode(), [MysqlDocs\NO_DATABASE, MysqlDocs\NO_USER, MysqlDocs\NO_TEMPLTE_FILE])) {
                $stdio->outln($e->getMessage());
            } else {
                throw $e;
            }
        }
    }
    
    
    public function output()
    {
        
        // 接続先の情報が足りない
        if (is_null($this->dsn)) {
            throw new \Exception('接続先の指定（dsn）が足りません。', MysqlDocs\NO_DSN);
        }
            
        // DBの指定が足りない
        if (is_null($this->database)) {
            throw new \Exception('データベースの指定が足りません。', MysqlDocs\NO_DATABASE);
        }
        
        // ユーザの指定がない
        if (is_null($this->user)) {
            throw new \Exception('ユーザの指定が足りません。', MysqlDocs\NO_USER);
        }
        
        // テンプレートの
        if (!file_exists($this->template) || !is_file($this->template)) {
            throw new \Exception($this->template.'は正しいパスではありません。', MysqlDocs\NO_TEMPLTE_FILE);
        }
        
        // 接続
        $pdo = new \PDO($this->dsn, $this->user, $this->password, [\PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8mb4']);
        
        // テーブル一覧を取得
        $statement = $pdo->prepare("SELECT * FROM `information_schema`.`TABLES` WHERE `TABLE_SCHEMA` = ?");
        $statement->execute([$this->database]);
        $tables = $statement->fetchAll(\PDO::FETCH_ASSOC);
        
        // Excelのテンプレートを用意
        $book = self::getExcelBook($this->template);
        
        // テーブルごとにシートをコピー
        foreach ($tables as $key => $table) {
            
            // シートをコピー
            $sheet = $book->getSheetByName('template')->copy();
            $sheet->setTitle($table['TABLE_NAME']);
            $book->addSheet($sheet);
            
            // インデックス
            $index = $book->getSheetByName('INDEX');
            $index->setCellValueByColumnAndRow(0, $key+5, $key+1);
            $index->setCellValueByColumnAndRow(1, $key+5, $table['TABLE_NAME']);
            $index->setCellValueByColumnAndRow(2, $key+5, $table['TABLE_COMMENT']);
            $index->getCellByColumnAndRow(1, $key+5)->getHyperlink()->setUrl("sheet://'".$table['TABLE_NAME']."'!A1");
            $index->getStyleByColumnAndRow(1, $key+5, 1, $key+5)->getFont()->getColor()->setRGB('0064FF');
            
            // 日付
            $now = new \DateTime();
            $index->setCellValue('C2', $now->format('Y/m/d H:i'));
            
            // テーブル名追加
            $sheet->setCellValueByColumnAndRow(0, 1, $table['TABLE_NAME']);
            $sheet->setCellValueByColumnAndRow(0, 2, $table['TABLE_COMMENT']);
            
            // カラムを取得
            $statement = $pdo->prepare("SELECT * FROM `information_schema`.`COLUMNS` WHERE `TABLE_SCHEMA` = ? AND `TABLE_NAME` = ?");
            $statement->execute([$table['TABLE_SCHEMA'], $table['TABLE_NAME']]);
            $rows = $statement->fetchAll(\PDO::FETCH_ASSOC);
            $i = 5;
            foreach ($rows as $row) {
                $sheet->setCellValueByColumnAndRow(0, $i, $row['COLUMN_NAME']);
                $sheet->setCellValueByColumnAndRow(1, $i, $row['COLUMN_COMMENT']);
                $sheet->setCellValueByColumnAndRow(2, $i, $row['COLUMN_TYPE']);
                $sheet->setCellValueByColumnAndRow(3, $i, $row['IS_NULLABLE']);
                $sheet->setCellValueByColumnAndRow(4, $i, $row['COLUMN_DEFAULT']);
                $sheet->setCellValueByColumnAndRow(5, $i, $row['EXTRA']);
                $sheet->setCellValueByColumnAndRow(6, $i, $row['COLUMN_KEY']);
                
                $sheet->getStyleByColumnAndRow(0, $i, 0, $i)->getFill()->setFillType(\PHPExcel_Style_Fill::FILL_SOLID)->getStartColor()->setRGB('f3f3f3');
                
                $i++;
            }
            
            // 外部キー
            $statement = $pdo->prepare("SELECT * FROM `information_schema`.`KEY_COLUMN_USAGE` WHERE `TABLE_SCHEMA` = ? AND `TABLE_NAME` = ? AND CONSTRAINT_NAME <> 'primary'");
            $statement->execute([$table['TABLE_SCHEMA'], $table['TABLE_NAME']]);
            $rows = $statement->fetchAll(\PDO::FETCH_ASSOC);
            if ($rows) {
                
                // ヘッダを挿入
                $i++;
                $sheet->setCellValueByColumnAndRow(0, $i, 'CONSTRAINT NAME');
                $sheet->setCellValueByColumnAndRow(1, $i, 'COLUMN');
                $sheet->setCellValueByColumnAndRow(2, $i, 'DATABASE');
                $sheet->setCellValueByColumnAndRow(3, $i, 'TABLE');
                $sheet->setCellValueByColumnAndRow(4, $i, 'COLUMN');
                $sheet->getStyleByColumnAndRow(0, $i, 4, $i)->getFill()->setFillType(\PHPExcel_Style_Fill::FILL_SOLID)->getStartColor()->setRGB('366C71');
                $sheet->getStyleByColumnAndRow(0, $i, 4, $i)->getFont()->getColor()->setRGB('FFFFFF');
                $sheet->getStyleByColumnAndRow(0, $i, 4, $i)->getAlignment()->setHorizontal(\PHPExcel_Style_Alignment::HORIZONTAL_CENTER);
                
                $i++;
                foreach ($rows as $row) {
                    $sheet->setCellValueByColumnAndRow(0, $i, $row['CONSTRAINT_NAME']);
                    $sheet->setCellValueByColumnAndRow(1, $i, $row['COLUMN_NAME']);
                    $sheet->setCellValueByColumnAndRow(2, $i, $row['REFERENCED_TABLE_SCHEMA']);
                    $sheet->setCellValueByColumnAndRow(3, $i, $row['REFERENCED_TABLE_NAME']);
                    $sheet->setCellValueByColumnAndRow(4, $i, $row['REFERENCED_COLUMN_NAME']);
                    $i++;
                }
            }
            
            $sheet->setCellValueByColumnAndRow(0, $i+1, '▲ 一覧に戻る');
            $sheet->getStyleByColumnAndRow(0, $i+1, 0, $i+1)->getFont()->getColor()->setRGB('0064FF');
            $sheet->getStyleByColumnAndRow(0, $i+1, 0, $i+1)->getFont()->applyFromArray(['name'=>'メイリオ']);
            $sheet->getCellByColumnAndRow(0, $i+1)->getHyperlink()->setUrl("sheet://'INDEX'!A1");
        }
        $book->removeSheetByIndex(1);
        
        $excel_writer = \PHPExcel_IOFactory::createWriter($book, 'Excel2007');
        $excel_writer->save($this->output);
    }
    
    
    /**
     * Excelのブックを取得
     * @param  string $templtae テンプレートとなるExcelファイルのパス
     * @return
     */
    public static function getExcelBook($template = null)
    {
        $book = null;
        if (!empty($template)) {
            $book = \PHPExcel_IOFactory::load($template);
        } else {
            $book = new \PHPExcel();
        }
        return $book;
    }
    
    
    public function __get($key)
    {
        if (in_array($key, self::$keys)) {
            if (array_key_exists($key, $this->props)) {
                return $this->props[$key];
            } else {
                return null;
            }
        }
    }
    
    
    public function __set($key, $val)
    {
        if (in_array($key, self::$keys)) {
            $this->props[$key] = $val;
        }
    }
    
    
    /**
     * インスタンスを生成
     * @param  array  $options dns: PDOに渡す接続情報
     *                         user: データベースのユーザ名
     *                         password: データベースのパスワード
     *                         database: データベース名
     *                         template: テンプレートとなるExcelファイルのパス
     *                         output: 出力するExcelファイルのパス
     * @return [type]          [description]
     */
    public static function factory(array $options)
    {
        $mysql_docs = new MysqlDocs();
        foreach (self::$keys as $key) {
            if (!empty($options[$key])) {
                $mysql_docs->{$key} = $options[$key];
            }
        }
        return $mysql_docs;
    }
}
