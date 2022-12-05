<?php
/**
 * 版本构建模型
 */

namespace FF\App\Admin\Model;

use FF\Extend\MyModel;

class VersionDistribModel extends MyModel
{
    public function __construct()
    {
        parent::__construct(DB_ADMIN, 't_version_distribution');
    }

}