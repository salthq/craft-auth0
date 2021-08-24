<?php

namespace salt\craftauth0\behaviors;

use craft\test\Craft;
use yii\base\Behavior;

class Auth0UserBehavior extends Behavior
{

    /** @var User */
    public $owner;

    public function getSub()
    {

        $result = (new \craft\db\Query()) 
        ->select(['sub']) 
        ->from('users') 
        ->where(['id' => $this->owner->id]) 
        ->one();
        return $result['sub'];
    }


    public function setSub($value)
    {
        return Craft::$app->db->createCommand()->update('users', ['sub' => $value], 'id = :id', [':id' => $this->owner->id])->execute();
    }

}