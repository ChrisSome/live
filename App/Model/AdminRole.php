<?php

namespace App\Model;

use App\Base\BaseModel;

class AdminRole extends BaseModel
{
	protected $tableName = 'admin_role';
	private $roleGroupDir = EASYSWOOLE_ROOT . DIRECTORY_SEPARATOR . 'App' . DIRECTORY_SEPARATOR . 'Utility' . DIRECTORY_SEPARATOR . 'RoleGroup' . DIRECTORY_SEPARATOR;
	
	/**
	 * @param $data
	 * @return bool
	 * @throws
	 */
	public function add($data): bool
	{
		$id = $this->insert($data);
		if ($id > 0) {
			$context = <<<EOF
<?php
namespace App\Utility\RoleGroup;
class RoleGroup{$id} extends RoleGroup
{

}
EOF;
			@file_put_contents($this->roleGroupDir . 'RoleGroup' . $id . '.php', $context);
			return true;
		}
		return false;
	}
}