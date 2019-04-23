<?php


namespace Talis\Manifesto;

require_once 'common.inc.php';

class Archive {
    /**
     * @var string
     */
    protected $id;

    /**
     * @var string
     */
    protected $status;

    /**
     * @var string
     */
    protected $location;

    public function loadFromJson($jsonDocument)
    {
        $this->loadFromArray(json_decode($jsonDocument, true));
    }

    public function loadFromArray(array $array)
    {
        if(isset($array['id']))
        {
            $this->id = $array['id'];
        }
        if(isset($array['status']))
        {
            $this->status = $array['status'];
        }

        if(isset($array['location']))
        {
            $this->location = $array['location'];
        }
    }

    /**
     * @return string
     */
    public function getStatus()
    {
        return $this->status;
    }

    /**
     * @return string
     */
    public function getLocation()
    {
        return $this->location;
    }

    /**
     * @return string
     */
    public function getId()
    {
        return $this->id;
    }

}