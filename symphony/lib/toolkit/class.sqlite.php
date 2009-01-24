<?php
	
	Class SQLite {
			
		const __IGNORE_OPERATION__ = -1;
		const __WRITE_OPERATION__ = 0;
		const __READ_OPERATION__ = 1;
		const __SET_OPERATION__ = 2;
		const __CREATE_OPERATION__ = 3;
		const __DROP_OPERATION__ = 4;
		const __ALTER_OPERATION__ = 5;
			
	    private $_connection = array();
	    private $_log;
	    private $_result;
	    private $_lastResult = array();
	    private $_lastQuery;
		private $_lastError;
	    private $_affectedRows;
	    private $_insertID;
		private $_dumpTables = array();
		private $_client_info;
		private $_client_encoding;
		private $_query_count;
		
		private $_cache;
		
	    function __construct(){
			$this->_query_count = 0;
			$this->_log = array('error' => array(), 'query' => array());
			$this->_cache = NULL;
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
	
		public function setPrefix($prefix){
	        $this->_connection['tbl_prefix'] = $prefix;
	    }
	
		public function isConnected(){
	        return (isset($this->_connection['id']) && $this->_connection['id'] !== NULL);
	    }
	    
		public function getSelected(){
	        return $this->_connection['database'];
	    }
		
		public function getConnectionResource(){
			return $this->_connection['rsrc'];
		}
		
		public function connect($host=NULL, $user=NULL, $password=NULL, $port ='3306'){

			$this->_connection['id'] = NULL;
			$this->_connection['rsrc'] = NULL;
			
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

	        return true;
	    }
		
		public function cleanValue($value) {
			if (function_exists('sqlite_escape_string')) {
				return sqlite_escape_string($value);
				
			} else {
				return addslashes($value);
			}
		}
		
		public function cleanFields(&$array){
			foreach($array as $key => $val){				
				if($val == '') $array[$key] = 'NULL';				
				else $array[$key] = "'".(function_exists('sqlite_escape_string') ? sqlite_escape_string($val) : addslashes($val))."'";
			}
		}
		
		public function insert($fields, $table, $updateOnDuplicate=false){

			// Multiple Insert
			if(is_array(current($fields))){

				$sql  = "INSERT INTO `$table` (`".implode('`, `', array_keys(current($fields))).'`) VALUES ';
				
				foreach($fields as $key => $array){
					$this->cleanFields($array);
					$rows[] = '('.implode(', ', $array).')';
				}
				
				$sql .= implode(", ", $rows);
				
			} 
			
			// Single Insert
			else{
				$this->cleanFields($fields);
				$sql  = "INSERT ".($updateOnDuplicate ? 'OR REPLACE' : '')." INTO `$table` (`".implode('`, `', array_keys($fields)).'`) VALUES ('.implode(', ', $fields).')';
			}

			return $this->query($sql);
		}
		
		public function update($fields, $table, $where=NULL){
			$this->cleanFields($fields);
			$sql = "UPDATE $table SET ";
			
			foreach($fields as $key => $val)
				$rows[] = " `$key` = $val";
			
			$sql .= implode(', ', $rows) . ($where != NULL ? ' WHERE ' . $where : NULL);
			
			return $this->query($sql);
		}
		
		public function delete($table, $where){
			$this->query("DELETE FROM '$table' WHERE $where");
		}
		
	    public function close(){
	        if($this->isConnected()) return @sqlite_close($this->_connection['rsrc']);	
	    }
	
		public function determineQueryType($query){
			if (!preg_match('/^(insert|replace|delete|update|optimize|truncate|set|create|drop|alter)/i', $query, $m))
				return self::__READ_OPERATION__;
			switch (strtolower($m[1])) {
				case 'insert':
				case 'replace':
				case 'delete':
				case 'update':
				case 'truncate':
					return self::__WRITE_OPERATION__;
				case 'set':
					return self::__SET_OPERATION__;
				case 'create':
					return self::__CREATE_OPERATION__;
				case 'drop':
					return self::__DROP_OPERATION__;
				case 'alter':
					return self::__ALTER_OPERATION__;
				case 'optimize':
					$query = preg_replace('/^OPTIMIZE TABLE ([^ ]*)/i', 'VACUUM;', $query);
					$temp = @sqlite_query($this->_connection['rsrc'], $query, SQLITE_NUM, $this->_lastError);	
					return self::__IGNORE_OPERATION__;
				default:
					return self::__READ_OPERATION__;
			}
		}
			
	    public function query($query){
		    if(empty($query)) return false;

			$queries = array();
			//$query = trim(str_replace("\\'", "''", preg_replace('/`([^`]+)`/', '[\\1]', preg_replace('/(^|\n)--[\w\W]*\n/U', '', $query))));
			// TODO: this is not safe - if script will use double quotes for text data, and that data will contain double quote...
			//       bad things may happen ;/
			$query = trim(str_replace("\\'", "''", str_replace('`', '"', preg_replace('/(^|\n)--[\w\W]*\n/U', '', $query))));
/* // Not needed since we registered md5 emulated function
			$query = preg_replace_callback('/ MD5\(\s*\'([^\']+)\'\s*\)/i', create_function(
            		// single quotes are essential here,
            		// or alternative escape all $ as \$
            		'$matches',
            		'return "\'".md5($matches[1])."\'";'
				), $query);
*/
			$query_type = $this->determineQueryType($query);
			if($query_type == self::__IGNORE_OPERATION__) return true;

			$noerror = false;
			if ($this->_client_info < 3.3 && ($query_type == self::__CREATE_OPERATION__ || $query_type == self::__DROP_OPERATION__)) {
				if (preg_match('/^(create|drop) table if (not |)exists/i', $query, $m)) {
					$query = str_replace('IF '.$m[2].'EXISTS', '', $query);
					$noerror = true;
				}
			}

			if ($query_type == self::__CREATE_OPERATION__) {

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
					//' \\1 TEXT CHECK (\\1 LIKE\'____-__-__ __:__:__\') ',
					' TEXT default "0000-00-00T00:00:00+00:00"',
					' ',
					' ',
					')'
				);

				// Newer SQLite have additional autoincrement option which works more like the one from MySQL
				if ($this->_client_info >= 3.1) $rplc[1] .= ' autoincrement';
				$query = preg_replace($find, $rplc, $query);

				// Add INDEX for UNIQUE, FULLTEXT and other KEYs
				if (preg_match_all('/,?\s+(UNIQUE|FULLTEXT|) KEY\s+"([^"]+)"\s+\(([^\)]+)\),?/U', $query, $m)) {
					preg_match('/"([^"]+)"\s*\(/', $query, $name);
					for ($i = 0; $i < count($m[2]); $i++) {
						$query = str_replace($m[0][$i], '', $query);
						$queries[] = 'CREATE '.($m[1][$i] == 'UNIQUE' ? 'UNIQUE' : '').' INDEX "'.$name[1].'.'.$m[2][$i].'" ON '.$name[1].' ('.$m[3][$i].')';
						// TODO: rewrite query for fulltext handling? http://phpadvent.org/2008/full-text-searching-with-sqlite-by-scott-macvicar
					}
				}

				// Add INDEX for PRIMARY KEY
				if (preg_match_all('/,?\s+PRIMARY KEY\s+\(([^\)]+)\),?/', $query, $m)) {
					preg_match('/"([^"]+)"\s*\(/', $query, $name);
					for ($i = 0; $i < count($m[1]); $i++) {
						$query = str_replace($m[0][$i], '', $query);
						$k = trim($m[1][$i], '"');
						if (preg_match('/"'.$k.'"\s+[^,]+PRIMARY KEY[^,]*,/', $query)) continue;
						$queries[] = 'CREATE UNIQUE INDEX "'.$name[1].'.'.$k.'" ON '.$name[1].' ('.$k.')';
					}
				}

				// Strip ENGINE, CHARSET, etc...
				$p = strrpos($query, ')');
				$query = trim(substr($query, 0, $p), ',').')';
			}

			// SQLite has to be compiled with SQLITE_ENABLE_UPDATE_DELETE_LIMIT to support "DELETE ... LIMIT X"
			// and there is no way to test that option at runtime :(.
			// That's why we have to rewrite such queries :(.
			if ($query_type == self::__WRITE_OPERATION__ && preg_match('/^DELETE\s+FROM\s+"([^"]+)"\s+(WHERE\s+[\w\W]+\s*LIMIT\s+\d+)$/', $query, $m)) {
				$query = 'DELETE FROM "'.$m[1].'" WHERE rowid IN (SELECT rowid FROM "'.$m[1].'" '.$m[2].')';
			}

			// SQLite ddoes not support MySQL's multiple-row insert syntax ('INSERT ... VALUES (...), (...), (...)'),
			// so we have to split it into additional queries;
			if(preg_match('/^INSERT/i', $query) && preg_match('/ VALUES ([\w\W]+)$/', $query, $m)) {
				$query = str_replace($m[0], '', $query);
				$a = str_split($m[1]);
				$insideQuote = '';
				$stack = '';
				$data = array();
				foreach($a as $c){
					switch($c) {
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

				$temp = $query;
				$query .= ' VALUES '.array_shift($data);
				foreach($data as $q){
					$queries[] = $temp.' VALUES '.$q;
				}
			}

			// TODO: emulate SET instructions if/when queries start to use SET variables
			if($query_type == self::__SET_OPERATION__) return true;

			/*
			// TODO: emulate caching?
			if($query_type == self::__READ_OPERATION__ && $this->isCachingEnabled() !== NULL && !preg_match('/^SELECT\s+SQL(_NO)?_CACHE/i', $query)){
				if($this->isCachingEnabled() === false) $query = preg_replace('/^SELECT\s+/i', 'SELECT SQL_NO_CACHE ', $query);
				elseif($this->isCachingEnabled() === true) $query = preg_replace('/^SELECT\s+/i', 'SELECT SQL_CACHE ', $query);
			}
			*/
			if($query_type == self::__READ_OPERATION__) $query = preg_replace('/^SELECT\s+SQL(_NO)?_CACHE/i', 'SELECT ', $query);
			
	        if($this->_connection['tbl_prefix'] != 'tbl_'){
	            $query = preg_replace('/tbl_(\S+?)([\s\.,]|$)/', $this->_connection['tbl_prefix'].'\\1\\2', $query);
	        }

			$query_hash = md5($query);
			
			$this->_log['query'][$query_hash] = array('query' => $query, 'start' => precision_timer());

	        $this->flush();
	        $this->_lastQuery = $query;

			$this->_result = @sqlite_query($this->_connection['rsrc'], $query, SQLITE_NUM, $this->_lastError);	

			$this->_query_count++;

	        if($this->_result === FALSE && !$noerror){
var_dump($query);
var_dump($this->_lastError);
exit();
	            $this->__error();
	            return false;
	        }

	        while ($row = @sqlite_fetch_object($this->_result)){
				// TODO: sqlite seems to keep quoting used for column names, e.g. [id] will be returned as result["[id]"] :(,
				//       so strip it here.
				//       Also strip table names (looks like sqlite doesn't strip table name from things like "table.column" :(.
				$row2 = NULL;
				foreach ($row as $k => $v) {
					$row2->{trim(substr($k, strrpos($k, '.')),'".')} = $v;
				}
	            @array_push($this->_lastResult, $row2);
	        }

	        if($query_type == self::__WRITE_OPERATION__){
					
	            $this->_affectedRows = @sqlite_changes($this->_connection['rsrc']);
					
	            if(stristr($query, 'insert') || stristr($query, 'replace')){
	                $this->_insertID = @sqlite_last_insert_rowid($this->_connection['rsrc']);
	            }
						
	        }
				
	        unset($this->_result);

			$this->_log['query'][$query_hash]['time'] = precision_timer('stop', $this->_log['query'][$query_hash]['start']);

			foreach ($queries as $query) {
				$temp = $this->_affectedRows;
				$this->query($query);
				$this->_affectedRows += $temp;
			}
			
	        return true;
				
	    }
		
		public function extractTargetTablesFromQuery($query){			
			if(!preg_match('/\\s+FROM\\s+(([\\w\\d\\-`\'_]+(,(\\s+)?)?)+)/i', $query, $matches)) return 'DUAL';
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

	        foreach ($this->_lastResult as $row){
	            $newArray[] = get_object_vars($row);
	        }		

			if($index_by_field && isset($newArray[0][$index_by_field])){
			
			  $n = array();
			  
			  foreach($newArray as $ii)
			      $n[$ii[$index_by_field]] = $ii;
			      
			  $newArray = $n;  
			
			}
			
	        return $newArray;
			
	    }
			
	    public function fetchRow($offset=0, $query=NULL){
	        $arr = $this->fetch($query);
	        return (empty($arr) ? array() : $arr[$offset]);
	
	    }
			
	    public function fetchCol ($name, $query = NULL){
	
	        $arr = $this->fetch($query);
	        
		    if(empty($arr)) return array(); 
				
	        foreach ($arr as $row){
	            $result[] = $row[$name];
	        }
				
	        return $result;
	
	    }	
			
	    public function fetchVar ($varName, $offset = 0, $query = NULL){
	
	        $arr = $this->fetch($query);
	        return (empty($arr) ? NULL : $arr[$offset][$varName]);
	        
	    }
			
	    public function flush(){
	
	        $this->_result = NULL;
	        $this->_lastResult = array();
	        $this->_lastQuery = NULL;
	
	    }
	
		public function flushLog(){
			$this->_log = array();
		}
			
	    private function __error($msg = NULL){

	        if(!$msg){
	            $msg = $this->_lastError;
	        }
				
	        $this->_log['error'][] = array ('query' => $this->_lastQuery,
	                               			'msg' => $msg);

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

			return array('queries' => $this->queryCount(),
						 'slow-queries' => $slow_queries,
						 'total-query-time' => number_format($query_timer, 4, '.', ''));

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

		// Emulate some of MySQL functions

		public function mysql_md5($s) {
			return md5($s);
		}

		public function mysql_unix_timestamp($s = NULL) {
			if ($s) return strtotime($s);
			return time();
		}
	}

