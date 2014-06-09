<?php

use Underscore\Types\Arrays;

Arrays::extend('all', function($array, $condition) {
  return Arrays::matches($array, $condition);
});

Arrays::extend('some', function($array, $condition) {
  return Arrays::matchesAny($array, $condition);
});

Arrays::extend('transform', function($array, $transform) {
  return Arrays::each($array, $transform);
});
