<?php
  /**
   * Created by JetBrains PhpStorm.
   * User: jphipps
   * Date: 1/14/12
   * Time: 11:33 AM
   * To change this template use File | Settings | File Templates.
   */
  class updateCheck
  {


    /** @var $lastSchemaUpdate integer **/
    private $lastSchemaUpdate;

    /** @var $lastVocabUpdate integer **/
    private $lastVocabUpdate;

      /** @var $lastChecked integer */
    private $lastChecked;

    public function __construct()
    {
      //check to see when the last time we checked was
      //if it was within the last x minutes
        //return the cached values
      //else
        //update the cached values
      //set it to now
    }

    public function setLastSchemaUpdate($lastSchemaUpdate)
    {
      $this->lastSchemaUpdate = $lastSchemaUpdate;
    }

    public function getLastSchemaUpdate()
    {
      return $this->lastSchemaUpdate;
    }

    public function setLastVocabUpdate($lastVocabUpdate)
    {
      $this->lastVocabUpdate = $lastVocabUpdate;
    }

    public function getLastVocabUpdate()
    {
      return $this->lastVocabUpdate;
    }

    public function setLastChecked($lastChecked)
    {
      $this->lastChecked = $lastChecked;
    }

    public function getLastChecked()
    {
      return $this->lastChecked;
    }
  }
