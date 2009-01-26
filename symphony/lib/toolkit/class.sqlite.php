<?php
	
	Class SQLite {
			
		private $_connection = array();
		private $_log;
		private $_lastResult = array();
		private $_lastQuery;
		private $_lastError;
		private $_affectedRows;
		private $_insertID;
		private $_optimize;
		private $_client_info;
		private $_client_encoding;
		private $_query_count;
		private $_cache;
		private $_logEverything;
		
		function __construct(){
			$this->_query_count = 0;
			$this->_cache = NULL;
			$this->_logEverything = NULL;
			$this->_optimize = false;
			$this->flush();
			$this->flushLog();
		}

		function __destruct(){
			$this->flush();
			$this->close();
		}
		
		public function toggleCaching(){
			$this->_cache = !$this->_cache;
		}
	
		public function enableCaching(){
			$this->_cache = true;
		}
		
		public function disableCaching(){
			$this->_cache = false;
		}

		public function isCachingEnabled(){
			return $this->_cache;
		}

		public function toggleLogging(){
			$this->_logEverything = !$this->_logEverything;
		}
	
		public function enableLogging(){
			$this->_logEverything = true;
		}
		
		public function disableLogging(){
			$this->_logEverything = false;
		}

		public function isLogging(){
			return $this->_logEverything;
		}

		public function setPrefix($prefix){
			$this->_connection['tbl_prefix'] = $prefix;
		}
	
		public function isConnected(){
			if(isset($this->_connection['rsrc'])){
				return ($this->_connection['rsrc'] !== FALSE);
			}
			return (isset($this->_connection['id']) && $this->_connection['id'] !== FALSE);
		}
		
		public function getSelected(){
			return $this->_connection['database'];
		}
		
		public function getConnectionResource(){
			return $this->_connection['rsrc'];
		}

		public function getLogs(){
			return $this->_log;
		}

		public function connect($host=NULL, $user=NULL, $password=NULL, $port ='3306'){

			$this->_connection['id'] = FALSE;
			unset($this->_connection['rsrc']);
			
			if($host) $this->_connection['host'] = $host;
			if($user) $this->_connection['user'] = $user;
			if($password) $this->_connection['pass'] = $password;
			if($port) $this->_connection['port'] = $port;

			if(!function_exists('sqlite_open')){
				$this->_lastError = __('SQLite is not available.');
				$this->__error();
				return false;
			}
			
			$this->_client_info = floatval(sqlite_libversion());
			$this->_client_encoding = sqlite_libencoding();

			$this->_connection['id'] = time();

			return true;
	
		}
		
		public function setCharacterSet($set='utf8'){
			//$this->query("SET CHARACTER SET '$set'");
			// TODO: add automatic conversion based on iconv
		}
		
		public function setCharacterEncoding($set='utf8'){
			//$this->query("SET NAMES '$set'");
			// TODO: add automatic conversion based on iconv
		}
			
		public function select($db=NULL){
			
			if($db) $this->_connection['database'] = $db;

			if($this->_connection['rsrc']) $this->close();

			global $settings;
			$this->_connection['rsrc'] = @sqlite_open(DOCROOT . '/' . md5(
				$this->_connection['user'] . ':' .
				$this->_connection['pass'] . '@' .
				$this->_connection['host'] . ':' .
				$this->_connection['port'] . '/' .
				$this->_connection['database']
			).'.sqlite', $settings['file']['write_mode'], $this->_lastError);
			
			if(!$this->isConnected()){
				$this->__error();
				$this->_connection['database'] = null;
				return false;
			}

			// Register emulated functions
			sqlite_create_function($this->_connection['rsrc'], 'MD5', array($this, 'mysql_md5'), 1);
			sqlite_create_function($this->_connection['rsrc'], 'UNIX_TIMESTAMP', array($this, 'mysql_unix_timestamp'));

			// Make sure we don't have to convert column names, because SQLite by default does not simplify them, like MySQL does
			// (so things like "SELECT table.column" or "SELECT table.'column'" return as $row["table.column"] or $row["table.'column'"]).
			@sqlite_exec($this->_connection['rsrc'], 'PRAGMA short_column_names = 1');
			@sqlite_exec($this->_connection['rsrc'], 'PRAGMA full_column_names = 0');

			return true;
		}
		
		public function cleanValue($value) {
			if(get_magic_quotes_gpc()) $value = stripslashes($value);
			return sqlite_escape_string($value);
		}
		
		public function cleanFields(&$array){
			$gpc = get_magic_quotes_gpc();
			foreach($array as $key => $val){				
				if($val == '') $array[$key] = 'NULL';				
				elseif($gpc) $array[$key] = "'".sqlite_escape_string(stripslashes($val))."'";
				else $array[$key] = "'".sqlite_escape_string($val)."'";
			}
		}
		
		public function insert($fields, $table, $updateOnDuplicate=false){

			// Multiple Insert
			if(is_array(current($fields))){

				$sql = "INSERT INTO `{$table}` (`".implode('`, `', array_keys(current($fields))).'`) VALUES ';
				
				foreach($fields as $key => $array){
					$this->cleanFields($array);
					$rows[] = '('.implode(', ', $array).')';
				}
				
				$sql .= implode(", ", $rows);
				
			} 
			
			// Single Insert
			else{
				$this->cleanFields($fields);
				$sql = "INSERT ".($updateOnDuplicate ? 'OR REPLACE' : '')." INTO `{$table}` (`".implode('`, `', array_keys($fields)).'`) VALUES ('.implode(', ', $fields).')';
			}

			return $this->query($sql);
		}
		
		public function update($fields, $table, $where=NULL){
			$this->cleanFields($fields);
			$sql = "UPDATE {$table} SET ";
			
			foreach($fields as $key => $val)
				$rows[] = " `{$key}` = {$val}";
			
			$sql .= implode(', ', $rows) . ($where != NULL ? ' WHERE ' . $where : NULL);
			
			return $this->query($sql);
		}
		
		public function delete($table, $where){
			$this->query("DELETE FROM `{$table}` WHERE {$where}");
		}
		
		public function close(){
			if(!$this->isConnected()) return true;

			if($this->_optimize) $this->_query('VACUUM', true);
			$this->_optimize = false;

			@sqlite_close($this->_connection['rsrc']);
			unset($this->_connection['rsrc']);

			return true;
		}

		public function query($query){
			if(empty($query)) return false;

			if($this->_connection['tbl_prefix'] != 'tbl_'){
				$query = preg_replace('/(?<=[^a-zA-Z])tbl_(\S+?)([\s\.,\]]|$)/', $this->_connection['tbl_prefix'].'\\1\\2', $query);
			}

			$query_hash = md5($query.time());
			$this->_log['query'][$query_hash] = array('query' => $query, 'start' => precision_timer());

			$result = false;

			// Strip MySQL comments
			// TODO: this does not remove comments between separate queries, e.g., "DELETE FROM ...; --COMMENT DELETE FROM ...;"
			$query = preg_replace('/^\s*\-\-[^\n]+\n/', "\n", $query);

			// TODO: check for subqueries and translate them too?
			if(preg_match('/^(create|drop|alter|insert|replace|update|select|delete|show|optimize|truncate|set)\s/i', trim($query), $m)){
				$f = '_mysql_'.strtolower($m[1]);

				if(method_exists($this, $f)){
					$result = $this->$f($query);
				}
			}

			if($result) $this->_log['query'][$query_hash]['time'] = precision_timer('stop', $this->_log['query'][$query_hash]['start']);

			if($this->_logEverything){
				$this->_log['query'][$query_hash]['lastQuery'] = $this->_lastQuery;
				$this->_log['query'][$query_hash]['lastResult'] = $this->_lastResult;
				$this->_log['query'][$query_hash]['affectedRows'] = $this->_affectedRows;
				$this->_log['query'][$query_hash]['insertID'] = $this->_insertID;
			}

			return $result;
		}

		// TODO: remove this obsolete function?
		public function extractTargetTablesFromQuery($query){			
			if(!preg_match('/\\s+FROM\\s+(([\\w\\d\\-`\[\]\'_]+(,(\\s+)?)?)+)/i', $query, $matches)) return 'DUAL';
			return $matches[1];
		}
			
		public function numOfRows(){
			return count($this->_lastResult);	
		}
			
		public function getInsertID(){
			return $this->_insertID;
		}
	
		public function queryCount(){
			return $this->_query_count;
		}
	
		public function fetch($query=NULL, $index_by_field=NULL){

			if($query) $this->query($query);
	
			elseif($this->_lastResult == NULL){
				return array();
			}

			if($index_by_field && isset($$this->_lastResult[0][$index_by_field])){
				$n = array();

				foreach($$this->_lastResult as $row)
					$n[$row[$index_by_field]] = $row;

				return $n;
			}

			return $this->_lastResult;
		}
			
		public function fetchRow($offset = 0, $query = NULL){
			$arr = $this->fetch($query);
			return (empty($arr) ? array() : $arr[$offset]);
		}
			
		public function fetchCol($name, $query = NULL){
			$arr = $this->fetch($query);
			if(empty($arr)) return array(); 

			foreach ($arr as $row){
				$result[] = $row[$name];
			}

			return $result;
		}	
			
		public function fetchVar($varName, $offset = 0, $query = NULL){
			$arr = $this->fetch($query);
			return (empty($arr) ? NULL : $arr[$offset][$varName]);
		}
			
		public function flush(){
			$this->_lastResult = array();
			$this->_lastQuery = NULL;
			$this->_lastError = NULL;
			$this->_affectedRows = 0;
			$this->_insertID = NULL;
		}
	
		public function flushLog(){
			$this->_log = array('error' => array(), 'query' => array());
		}
			
		private function __error($msg = NULL){
			if(!$msg){
				$num = @sqlite_last_error($this->_connection['rsrc']);
				$msg = $this->_lastError;
			}

			$this->_log['error'][] = array('query' => $this->_lastQuery, 'msg' => $msg, 'num' => $num);

			trigger_error(__('SQLite Error: %1$s in query "%2$s"', array($msg, $this->_lastQuery)), E_USER_WARNING);
		}

		public function debug($section=NULL){			
			if(!$section) return $this->_log;
			return ($section == 'error' ? $this->_log['error'] : $this->_log['query']);
		}
	
		public function getLastError(){
			@rewind($this->_log['error']);
			return current($this->_log['error']);
		}
		
		public function getStatistics(){
			$stats = array();
			
			$query_log = $this->debug('query');
			$query_timer = 0.0;
			$slow_queries = array();
			foreach($query_log as $key => $val)	{
				$query_timer += floatval($val['time']);
				if($val['time'] > 0.0999) $slow_queries[] = $val;
			}				

			return array(
				'queries' => $this->queryCount(),
				'slow-queries' => $slow_queries,
				'total-query-time' => number_format($query_timer, 4, '.', ''),
			);
		}
		
		public function import($sql){
			$queries = preg_split('/;[\\r\\n]+/', $sql, -1, PREG_SPLIT_NO_EMPTY);

			if(is_array($queries) && !empty($queries)){								
				foreach($queries as $sql){
					if(trim($sql) != '') $result = $this->query($sql);
					if(!$result) return false;
				}
			}

			return true;
		}

		private function _query($query, $noResults = false, $noError = false){
			if(is_array($query)){
				$result = true;
				foreach ($query as $q) {
					$temp = $this->_affectedRows;
					if(!$this->_query($q, $noResults, $noError)) $result = false;
					$this->_affectedRows += $temp;
				}
				return $result;
			}
			else if (!trim($query)) return false;

			$this->flush();
			$this->_lastQuery = $query;

			if($noResults)
				$result = @sqlite_exec($this->_connection['rsrc'], $query, $this->_lastError);	
			else
				$result = @sqlite_unbuffered_query($this->_connection['rsrc'], $query, SQLITE_NUM, $this->_lastError);	

			$this->_query_count++;

			if($result === FALSE && !$noError){
				$this->__error();
				return false;
			}

			if(!$noResults && $result !== FALSE && sqlite_has_more($result)){
				while($row = sqlite_fetch_array($result, SQLITE_ASSOC)){
					@array_push($this->_lastResult, $row);
				}
			}

			if($noResults && $result !== FALSE && preg_match('/^(INSERT|REPLACE|UPDATE|DELETE)\s/i', $query, $m)){
				$this->_affectedRows = @sqlite_changes($this->_connection['rsrc']);

				if(in_array(strtoupper($m[1]), array('INSERT','REPLACE'))){
					$this->_insertID = @sqlite_last_insert_rowid($this->_connection['rsrc']);
				}
			}
				
			return true;
		}

		// Emulate some of MySQL functions

		public function mysql_md5($s){
			return md5($s);
		}

		public function mysql_unix_timestamp($s = NULL){
			if($s) return strtotime($s);
			return time();
		}

		// MySQL query translation

		private function _mysql_escape($query){
			// Following line changes MySQL type of escape (\') to sqlite way (''),
			// and changes MySQL's backticks to SQLite's square brackets.
			// TODO: backticks part is not safe - it should convert only backticks which wrap table and column names
			//		(now it converts all of them, including those inside of data/values).
			return trim(str_replace("\\'", "''", preg_replace('/`([^`]+)`/', '[$1]', $query)));
		}

		private function _table_exists($name){
			$name = $this->cleanValue(trim($m[2], " \t\n\r\0\x0B`'\"[]"));
			return ($this->_query("SELECT COUNT(*) AS found FROM sqlite_master WHERE type = 'table' AND name = '{$name}'") && count($this->_lastResult) > 0 && intval($this->_lastResult['found']) > 0);
		}

		private function _mysql_show($query){
			$query = $this->_mysql_escape($query);

			// TODO: extract database name and temporary open that database if it's not current one?
			if(!preg_match('/SHOW\s+(?:FULL\s+)?TABLES\s+(?:FROM\s+[^\s]+)?(LIKE\s+[\'"][^\'"]+[\'"])?/i', $query, $m)) return false;

			$query = 'SELECT name AS "Tables_in_'.$this->connection['database'].'" FROM '.
					'(SELECT * FROM sqlite_master UNION ALL SELECT * FROM sqlite_temp_master) WHERE type = \'table\'';
			if(isset($m[2])){
				$query .= ' AND name '.$m[2];
			}

			return $this->_query($query.' ORDER BY name');
		}

		private function _mysql_create($query){
			$query = $this->_mysql_escape($query);

			$tableName = array();
			$noError = false;

			if($this->_client_info < 3.3 && preg_match('/^CREATE\s+(?:TEMPORARY\s+)?TABLE\s+(?:IF\s+NOT\s+EXISTS\s+)([^\(\s]+)\s+(?:LIKE\s+|\()/iU', $query, $tableName)) {
				if($this->_table_exists($tableName[1])) return true;

				$query = preg_replace('/^CREATE\s+(TEMPORARY\s+|)TABLE\s+IF NOT EXISTS\s+/iU', 'CREATE $1 TABLE ', $query);
				$noError = true;
			}

			$find = array(
				'/ unsigned /i',
				'/ auto_increment/i',
				'/ smallint\([0-9]*\) /i',
				'/ tinyint\([0-9]*\) /i',
				'/ int\([0-9]+\)? /i',
				'/ character set [^ ]* /i',
				'/ enum\([^)]*\) /i',
				'/ datetime (default \'[^\']+\')?/i',
				'/ on update [^,]*/i',
				'/ collate [^\s]+ /i',
				'/,\s*\)$/',
			);
			$rplc = array(
				' ',
				' PRIMARY KEY',
				' INTEGER ',
				' INTEGER ',
				' INTEGER ',
				' ',
				' varchar(255) ',
				' TEXT default "0000-00-00T00:00:00+00:00"',//' \\1 TEXT CHECK (\\1 LIKE\'____-__-__ __:__:__\') ',
				' ',
				' ',
				')'
			);

			// Newer SQLite have additional autoincrement option which works more like the one from MySQL
			if ($this->_client_info >= 3.1) $rplc[1] .= ' autoincrement';
			$query = preg_replace($find, $rplc, $query);

			// Add INDEX for UNIQUE, FULLTEXT and other KEYs
			if (preg_match_all('/,?\s+(UNIQUE|FULLTEXT|) KEY\s+\[?([^\]]+)\]?\s+\(([^\)]+)\),?/U', $query, $m)) {
				if(empty($tableName)) preg_match('/^CREATE\s+(?:TEMPORARY\s+)?TABLE\s+(?:IF\s+NOT\s+EXISTS\s+)?([^\(\s]+)\s+(?:LIKE\s+|\()/iU', $query, $tableName);
				for ($i = 0; $i < count($m[2]); $i++) {
					$query = str_replace($m[0][$i], '', $query);
					$queries[] = 'CREATE '.($m[1][$i] == 'UNIQUE' ? 'UNIQUE' : '').' INDEX "'.$tableName[1].'.'.$m[2][$i].'" ON '.$tableName[1].' ('.$m[3][$i].')';
					// TODO: rewrite query for fulltext handling? http://phpadvent.org/2008/full-text-searching-with-sqlite-by-scott-macvicar
				}
			}

			// Add INDEX for PRIMARY KEY
			if (preg_match_all('/,?\s+PRIMARY KEY\s+\(([^\)]+)\),?/', $query, $m)) {
				if(empty($tableName)) preg_match('/^CREATE\s+(?:TEMPORARY\s+)?TABLE\s+(?:IF\s+NOT\s+EXISTS\s+)?([^\(\s]+)\s+(?:LIKE\s+|\()/iU', $query, $tableName);
				for ($i = 0; $i < count($m[1]); $i++) {
					$query = str_replace($m[0][$i], '', $query);
					$k = trim($m[1][$i], '"');
					if (preg_match('/"'.$k.'"\s+[^,]+PRIMARY KEY[^,]*,/', $query)) continue;
					$queries[] = 'CREATE UNIQUE INDEX "'.$tableName[1].'.'.$k.'" ON '.$tableName[1].' ('.$k.')';
				}
			}

			// Strip ENGINE, CHARSET, etc...
			$p = strrpos($query, ')');
			$query = trim(substr($query, 0, $p), " \t\n\r\0\x0B,").')';

			array_unshift($queries, $query);

			return $this->_query($queries, true, $noError);
		}

		private function _mysql_alter($query){
			// TODO:
			// 1. parse changes
			// 2. get schema of old table and create it's copy (including data)
			// 3. drop old table
			// 4. apply changes to schema
			// 5. build new table using changed schema
			// 6. copy data from temporary table to new table
			// 7. drop temporary table
			return false;
		}

		private function _mysql_drop($query){
			$query = $this->_mysql_escape($query);

			if(!preg_match('/DROP\s+(TEMPORARY\s+|)TABLE\s+(IF EXISTS\s+|)([^\s,]+(\s*,\s*[^\s,]+)*)(?:\s+RESTRICT|\s+CASCADE)/i', $query, $m)){
				return true;
			}

			if(trim($m[1])) return false; // AFAIK SQLite does not have a way to drop temporary table, except for closing and reopening connection ;/

			$noError = ($this->_client_info < 3.3 && trim($m[2]) ? true : false);

			$queries = array();
			foreach(explode(',', $m[3]) as $n){
				$queries[] = 'DROP TABLE "'.trim($n, " \t\n\r\0\x0B,").'"';
			}

			return $this->_query($queries, true, $noError);
		}

		private function _mysql_insert($query){
			$query = $this->_mysql_escape($query);

			// TODO: support "update" like syntax: http://dev.mysql.com/doc/refman/5.0/en/replace.html

			// SQLite does not support MySQL's multiple-row insert syntax ('INSERT ... VALUES (...), (...), (...)'),
			// so we have to split it into multiple queries.
			// TODO: this is not so safe, because value can contain ')' and screw up whole regexp matching
			if(preg_match('/ VALUES (\([\w\W]+\)(\s*,\s*\([\w\W]+\))+)$/U', $query, $m)){
				$query = str_replace($m[0], '', $query);
				$a = str_split($m[1]);
				$insideQuote = '';
				$stack = '';
				$data = array();
				foreach($a as $c){
					switch($c){
						case '(':
							if(!$insideQuote) $stack = '(';
							else $stack .= $c;
							break;
						case ')':
							$stack .= $c;
							if(!$insideQuote){
								$data[] = $stack;
								$stack = '';
							}
							break;
						case '\'':
							if($insideQuote==$c) $insideQuote = '';
							elseif(!$insideQuote) $insideQuote = $c;
							$stack .= $c;
							break;
						case '"':
							if($insideQuote==$c) $insideQuote = '';
							elseif(!$insideQuote) $insideQuote = $c;
							$stack .= $c;
							break;
						default:
							$stack .= $c;
							break;
					}
				}

				foreach($data as $q){
					$queries[] = $query.' VALUES '.$q;
				}

				$query = $queries;
			}

			return $this->_query($query, true);
		}

		private function _mysql_replace($query){
			return $this->_mysql_insert(preg_replace('/^REPLACE\s+(LOW_PRIORITY\s+|DELAYED\s+)?/', 'INSERT OR REPLACE ', $query));
		}

		private function _mysql_update($query){
			$query = $this->_mysql_escape($query);

			$query = preg_replace('/^UPDATE\s+(LOW_PRIORITY\s+)?IGNORE\s/iU', 'UPDATE OR IGNORE ', $query);

			// SQLite has to be compiled with SQLITE_ENABLE_UPDATE_DELETE_LIMIT to support "UPDATE ... ORDER BY ... LIMIT X".
			// There is no way to test that option at runtime, so we have to rewrite such queries :(.
			if(preg_match('/\s(ORDER\s+BY|LIMIT)\s/i', $query) && preg_match('/^UPDATE\s+(?:OR IGNORE\s+)?([^\s,]+)\s+SET/i', $query, $m)){
				$temp = explode(' WHERE ', $query, 2);
				$query = $temp[0].' WHERE rowid IN (SELECT rowid FROM '.$m[1].' WHERE '.$temp[1].')';
			}

			// TODO: Multiple-table syntax: http://dev.mysql.com/doc/refman/5.0/en/update.html

			return $this->_query($query, true);
		}

		private function _mysql_select($query){
			$query = $this->_mysql_escape($query);

			/*
			// TODO: emulate caching?
			if($query_type == self::__READ_OPERATION__ && $this->isCachingEnabled() !== NULL && !preg_match('/^SELECT\s+SQL(_NO)?_CACHE/i', $query)){
				if($this->isCachingEnabled() === false) $query = preg_replace('/^SELECT\s+/i', 'SELECT SQL_NO_CACHE ', $query);
				elseif($this->isCachingEnabled() === true) $query = preg_replace('/^SELECT\s+/i', 'SELECT SQL_CACHE ', $query);
			}
			*/
			$query = preg_replace('/^SELECT\s+SQL(_NO)?_CACHE/i', 'SELECT ', $query);

			return $this->_query($query);
		}

		private function _mysql_delete($query){
			$query = $this->_mysql_escape($query);

			// SQLite has to be compiled with SQLITE_ENABLE_UPDATE_DELETE_LIMIT to support "DELETE ... LIMIT X".
			// There is no way to test that option at runtime, so we have to rewrite such queries :(.
			if(preg_match('/^DELETE\s+(?:LOW_PRIORITY\s+)?(?:QUICK\s+)?(?:IGNORE\s+)?FROM\s+([^\s,]+)\s+WHERE\s+([\w\W]+)\s+(ORDER BY|LIMIT)\s+/iU', $query, $m)){
				$temp = explode(' WHERE ', $query, 2);
				$query = 'DELETE FROM '.$m[1].' WHERE rowid IN (SELECT rowid FROM '.$m[1].' WHERE '.$temp[1].')';
			}
			// TODO: Multiple-table syntax: http://dev.mysql.com/doc/refman/5.0/en/delete.html

			return $this->_query($query, true);
		}

		private function _mysql_optimize($query){
			$this->_optimize = true;
			return true;
		}

		private function _mysql_truncate($query){
			$query = $this->_mysql_escape($query);

			return $this->_query(preg_replace('/^TRUNCATE\s+(TABLE\s+)?/', 'DELETE FROM ', $query), true);
		}

		private function _mysql_set($query){
			// TODO: emulate MySQL commands through PRAGMA?
			// TODO: emulate variables with $this->_variables and additional translation applied to every query?
			return false;
		}
	}

