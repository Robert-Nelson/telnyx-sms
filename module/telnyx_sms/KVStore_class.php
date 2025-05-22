<?php

namespace FreePBX\modules;

class KVStore extends DB_Helper
{
  public function __construct($freepbx = null)
  {
    if ($freepbx == null) {
      throw new Exception('Not given a FreePBX Object');
    }

    $this->FreePBX = $freepbx;
  }

  public function insert($key, $value)
  {
    $this->setConfig($key, $value);
  }


  public function get($key)
  {
    return $this->getConfig($key);
  }


  public function delete($key)
  {
    $this->setConfig($key);
  }

  public function insertGroup($id, $itemsArray)
  {
    foreach ($itemsArray as $key => $value) {
      $this->setConfig($key, $value, $id);
    }
  }

  public function getGroup($id)
  {
    return $this->getAll($id);
  }

  public function deleteGroup($id)
  {
    $this->delById($id);
  }
}
