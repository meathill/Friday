<?php
/**
 * Created by PhpStorm.
 * User: 路佳
 * Date: 2015/2/6
 * Time: 17:17
 */

namespace Meathill;

use RecursiveArrayIterator;
use RecursiveIteratorIterator;

class ArrayUtils {
  /**
   * 从一个数组中择出来需要的
   *
   * @param $array
   *
   * @return array
   */
  public static function array_pick($array) {
    if (!is_array( $array )) {
      return $array;
    }
    $keys = array_slice(func_get_args(), 1);
    $keys = self::array_flatten($keys);
    $pick = array();
    foreach ( $keys as $key ) {
      if (!array_key_exists($key, $array)) {
        continue;
      }
      $pick[$key] = $array[$key];
    }
    return $pick;
  }

  public static function array_omit($array) {
    if (!is_array( $array )) {
      return $array;
    }
    $keys = array_slice(func_get_args(), 1);
    $keys = self::array_flatten($keys);
    $pick = array();
    foreach ( $array as $key => $value ) {
      if (in_array($key, $keys, true)) {
        continue;
      }
      $pick[$key] = $value;
    }
    return $pick;
  }

  public static function array_flatten($array){
    return iterator_to_array(new RecursiveIteratorIterator(new RecursiveArrayIterator($array)), false);
  }

  /**
   * 以递归的形式遍历一个数组，审查每一个对象
   * @param $array
   * @return array
   */
  public static function array_strip_tags($array) {
    $result = array();

    foreach ( $array as $key => $value ) {
      $key = strip_tags($key);

      if (is_array($value)) {
        $result[$key] = self::array_strip_tags($value);
      } else if (is_numeric($value) && !preg_match('/^0\d+$/', $value)) {
        $result[$key] = $value + 0;
      } else {
        $result[$key] = htmlspecialchars(trim(strip_tags($value)), ENT_QUOTES | ENT_HTML5);
      }
    }

    return $result;
  }

  /**
   * 遍历一个数组,检查其中的元素是否都符合检查函数的要求
   *
   * @param array $result
   * @param callable $callable
   *
   * @return bool
   */
  public static function array_all( array $result, callable $callable ) {
    foreach ( $result as $value ) {
      $check = $callable($value);
      if (!$check) {
        return $check;
      }
    }
    return true;
  }

  /**
   * 对数组进行排序
   *
   * @param array $array 目标数组
   * @param array|string $order 排序的键值
   * @param string $seq OPTIONAL 正序/倒序
   *
   * @return array
   */
  public static function array_sort(array $array, $order, $seq = '') {
    if (!$order) {
      return $array;
    }
    if (is_array($order)) {
      $key = array_keys($order)[0];
      $seq = $order[$key];
      $order = $key;
    }
    $seq = strtoupper($seq) == 'DESC' ? -1 : 1;
    usort( $array, function ($a, $b) use ($order, $seq) {
      $a = $a[$order];
      $b = $b[$order];
      $diff = is_string($a) && is_string($b) ? strcmp($a, $b) : $a - $b;
      return $seq * ceil($diff);
    });
    return $array;
  }

  /**
   * @param string $seq
   * @param string $defaults
   *
   * @return string
   */
  public static function get_seq($seq, $defaults = 'DESC') {
    return in_array( strtoupper( $seq ), [ 'DESC', 'ASC' ] ) ? $seq : $defaults;
  }

  /**
   * 判断一个数组是不是只有数字索引
   * 因为很多时候是配合js,所以直接判断最后一个键是不是等于最大长度-1就行了
   * 
   * @param array $array
   *
   * @return bool
   */
  public static function isSequentialArray(array $array) {
    if (is_array($array) && !$array) { // 接受空数组
      return true;
    }
    $keys = array_keys($array);
    return array_pop($keys) === count($array) - 1;
  }
}