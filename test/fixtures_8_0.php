<?php

class TestInstance {

}

class InjectorTestNullableParams
{
  public ?TestInstance $instance;

  public function __construct(?TestInstance $instance = null)
  {
    $this->instance = $instance;
  }
}