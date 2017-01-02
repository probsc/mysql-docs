<?php

namespace Test\NanairoWs\MyssqlDocs;
use NanairoWs\MysqlDocs;

class MyssqlDocsTest extends \PHPUnit_Framework_TestCase {
	
	private $template = 'src/template.xlsx';
	private $output = 'output.xlsx';
	
	
	/**
	 * @expectedException     \Exception
	 * @expectedExceptionCode 10002
	 */
	public function testFacotry() {
		
		$mysql_docs = MysqlDocs::factory([
			'dsn' => MYSQL_DSN,
			'user' => MYSQL_USER,
			'password' => MYSQL_PASS.'-pass',
			'database' => MYSQL_DB,
			'template' => $this->template,
			'output' => $this->output
		]);
		
		$this->assertEquals($mysql_docs->dsn, MYSQL_DSN);
		$this->assertEquals($mysql_docs->user, MYSQL_USER);
		$this->assertEquals($mysql_docs->password, MYSQL_PASS.'-pass');
		$this->assertEquals($mysql_docs->database, MYSQL_DB);
		$this->assertEquals($mysql_docs->template, $this->template);
		$this->assertEquals($mysql_docs->output, $this->output);
		
		// 間違ったファイル
		$mysql_docs->template = 'invalid';
		$mysql_docs->output();
	}
	
	
	/**
	 * プロパティのセット
	 */
	public function testProperty() {
		
		$mysql_docs = new MysqlDocs;
		
		$mysql_docs->dsn = MYSQL_DSN;
		$mysql_docs->user = MYSQL_USER;
		$mysql_docs->password = MYSQL_PASS.'-pass';
		$mysql_docs->database = MYSQL_DB;
		$mysql_docs->template = $this->template;
		$mysql_docs->output = $this->output;
		
		$this->assertEquals($mysql_docs->dsn, MYSQL_DSN);
		$this->assertEquals($mysql_docs->user, MYSQL_USER);
		$this->assertEquals($mysql_docs->password, MYSQL_PASS.'-pass');
		$this->assertEquals($mysql_docs->database, MYSQL_DB);
		$this->assertEquals($mysql_docs->template, $this->template);
		$this->assertEquals($mysql_docs->output, $this->output);
		
	}
	
	
	/**
	 * @expectedException     \Exception
	 * @expectedExceptionCode 10003
	 */
	public function testNoDb() {
		
		$mysql_docs = $mysql_docs = MysqlDocs::factory([]);
		$mysql_docs->user = MYSQL_USER;
		$mysql_docs->dsn = MYSQL_DSN;
		$mysql_docs->output();
	}
	
	
	/**
	 * @expectedException     \Exception
	 * @expectedExceptionCode 10004
	 */
	public function testNoUser() {
		
		$mysql_docs = $mysql_docs = MysqlDocs::factory([]);
		$mysql_docs->database = MYSQL_DB;
		$mysql_docs->dsn = MYSQL_DSN;
		$mysql_docs->output();
	}
	
	
	/**
	 * @expectedException     \Exception
	 * @expectedExceptionCode 10005
	 */
	public function testNoDsn() {
		
		$mysql_docs = $mysql_docs = MysqlDocs::factory([]);
		$mysql_docs->database = MYSQL_DB;
		$mysql_docs->user = MYSQL_USER;
		$mysql_docs->output();
	}
	
	
	/**
	* 正常系のテスト
	 */
	public function testValid() {
		
		// テスト用のテーブルを用意する
		$table = 'mysqldocs_test_'.date('Ymd-His');
		$create = sprintf("CREATE TABLE `%s` (
			`id` int(11) NOT NULL AUTO_INCREMENT,
			`title` varchar(100) NOT NULL COMMENT '名称',
			`created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '登録時間',
			PRIMARY KEY (`id`)
		) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='テストテーブル'", $table);
		$pdo = new \PDO(MYSQL_DSN, MYSQL_USER, MYSQL_PASS, [\PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8mb4']);
		$use_db = sprintf('use `%s`', MYSQL_DB);
		if (!$pdo->query($use_db)) {
			$pdo->query(sprintf('create database `%s`', MYSQL_DB));
			$pdo->query($use_db);
		}
		$pdo->query($create);
		
		$mysql_docs = $mysql_docs = MysqlDocs::factory([
			'dsn' => MYSQL_DSN,
			'user' => MYSQL_USER,
			'password' => MYSQL_PASS,
			'database' => MYSQL_DB
		]);
		
		$mysql_docs->output();
		
		// 確認
		$book = \PHPExcel_IOFactory::load($mysql_docs->output);
		
		$s_index = $book->getSheetByName('INDEX');
		$this->assertEquals($s_index->getCell('B5')->getValue(), $table);
		$this->assertEquals($s_index->getCell('C5')->getValue(), 'テストテーブル');
		
		$s_table = $book->getSheetByName($table);
		$this->assertEquals($s_table->getCell('A1')->getValue(), $table);
		
		$this->assertEquals($s_table->getCell('A5')->getValue(), 'id');
		$this->assertEquals($s_table->getCell('B5')->getValue(), '');
		$this->assertEquals($s_table->getCell('C5')->getValue(), 'int(11)');
		$this->assertEquals($s_table->getCell('D5')->getValue(), 'NO');
		$this->assertEquals($s_table->getCell('E5')->getValue(), '');
		$this->assertEquals($s_table->getCell('F5')->getValue(), 'auto_increment');
		$this->assertEquals($s_table->getCell('G5')->getValue(), 'PRI');
		
		$this->assertEquals($s_table->getCell('A6')->getValue(), 'title');
		$this->assertEquals($s_table->getCell('B6')->getValue(), '名称');
		$this->assertEquals($s_table->getCell('C6')->getValue(), 'varchar(100)');
		$this->assertEquals($s_table->getCell('D6')->getValue(), 'NO');
		$this->assertEquals($s_table->getCell('E6')->getValue(), '');
		$this->assertEquals($s_table->getCell('F6')->getValue(), '');
		$this->assertEquals($s_table->getCell('G6')->getValue(), '');
		
		$this->assertEquals($s_table->getCell('A7')->getValue(), 'created_at');
		$this->assertEquals($s_table->getCell('B7')->getValue(), '登録時間');
		$this->assertEquals($s_table->getCell('C7')->getValue(), 'timestamp');
		$this->assertEquals($s_table->getCell('D7')->getValue(), 'NO');
		$this->assertEquals($s_table->getCell('E7')->getValue(), 'CURRENT_TIMESTAMP');
		$this->assertEquals($s_table->getCell('F7')->getValue(), '');
		$this->assertEquals($s_table->getCell('G7')->getValue(), '');
		
		$this->assertEquals($s_table->getCell('A9')->getValue(), '▲ 一覧に戻る');
		
		// テスト用のテーブルと生成されたファイルを削除
		$pdo->query(sprintf('drop table `%s`', $table));
		unlink($mysql_docs->output);
	}
	
	
	/**
	* @expectedException     \PDOException
	 */
	public function testInvalidDsn() {
		
		$mysql_docs = $mysql_docs = MysqlDocs::factory([
			'dsn' => MYSQL_DSN,
			'user' => MYSQL_USER,
			'password' => MYSQL_PASS,
			'database' => MYSQL_DB,
			'template' => $this->template,
			'output' => $this->output
		]);
		
		// 不正なDSN
		$mysql_docs->dsn = 'invalid';
		$mysql_docs->output();
	}
	
	
	
	public function testCli() {
		
		$this->assertEquals(PHP_SAPI, 'cli');
		
		$p = proc_open(__dir__.'/../../../app/mysql-docs', [
			0 => ['pipe', 'r'],
			1 => ['pipe', 'w'],
			2 => ['pipe', sys_get_temp_dir().'/error-output.txt']
		], $pipes);
		
		if (!is_resource($p)) {
			throw new \Exception('実行できませんでした。');
		}
		fwrite($pipes[0], MYSQL_PASS);
		fclose($pipes[1]);
		
	}
	
}
