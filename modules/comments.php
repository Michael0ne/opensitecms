<?

class Comments extends Base {
	function LoadComments($params = null) {
		if (empty($params))
			return null;

		try {
			$comments = $this->DB_Query("SELECT * FROM `catalog_comments` WHERE `itemid` = " . $params[0], "utf8");
		} catch (Exception $e) {
			$this->LogError($e->getMessage());
			$this->error = $e->getMessage();
		}

		return $comments ? $comments : null;
	}
}

?>