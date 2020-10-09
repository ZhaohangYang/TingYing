<?php

class A
{
}

class B
{
    public function __construct(A $a, $prarms = [])
    {
        # code...
    }
}

class C
{
    public function __construct(B $b)
    {
        # code...
    }
}
