<?php

/**
 * Created by PhpStorm.
 * User: meathill
 * Date: 16/6/22
 * Time: 下午6:27
 */
namespace Meathill;

use GuzzleHttp\Client;
use PHPUnit_Framework_TestCase;

class Friday extends PHPUnit_Framework_TestCase {
  protected static $datetimeReg = '/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/';
  protected static $dateReg = '/^\d{4}-\d{2}-\d{2}$/';
  protected static $timeReg = '/^\d{2}:\d{2}:\d{2}$/';
  protected static $header = [
    'User-Agent' => 'dianjoy/1.0',
    'Accept' => 'application/json',
  ];
  protected static $response = [
    'statusCode' => 200,
  ];
  protected static $API_URL = '';

  /**
   * @var Client
   */
  protected $client;
  protected $cookie;
  protected $config;
  protected $configJSON = 'api.json';
  protected $name = 'Friday';
  protected $hookMap = [];
  protected $path = null;
  protected $session = [];

  protected function setUp() {
    $this->client = new Client([
      'base_uri' => self::$API_URL,
      'cookies' => true,
    ]);
    $this->client->request('GET', 'set_session.php', [
      'query' => $this->session,
    ]);
    $this->config = json_decode(file_get_contents($this->configJSON), true);
  }

  /**
   * 编辑描述文件,对每个接口的每个输入输出定义做校验。
   */
  protected function doTest() {
    foreach ( $this->config as $api => $tests ) {      
      foreach ( $tests as $test ) {
        $this->validateAPI( $test, $api );
      }
    }
  }

  /**
   * 按照描述文件中的应以生成请求的内容。
   *
   * @param $input
   *
   * @return array
   */
  protected function generateRequestOptions( $input ) {
    $options = [
      'dataType' => 'json',
      'headers' => array_merge(self::$header, (array)$input['header']),
    ];
    $options = array_merge($options, ArrayUtils::array_pick($input, 'json', 'form', 'query'));
    return $options;
  }

  /**
   * 校验 API
   *
   * @param string $test
   * @param string $api
   */
  protected function validateAPI( $test, $api ) {
    $method = $test['method'];
    $options = $this->generateRequestOptions((array)$test['input']);
    $expectedResponse = array_merge(self::$response, (array)$test['response']);
    $response = $this->client->request( $method, $api, $options );

    $this->assertEquals($expectedResponse['statusCode'], $response->getStatusCode());

    $this->path = ["$method $api"];
    $body = $response->getBody();
    if ($options['dataType'] === 'json') {
      $this->assertRegExp('/application\/json/i', $response->getHeader('Content-type')[0], $this->createErrorMessage('返回的数据应该是 JSON 格式，而 HTTP header 不是。'));
      $body = json_decode($body, true);
      $this->assertNotNull($body, '返回的数据不是 JSON 格式；或嵌套数太多无法解析。');
      $this->validateJSON($body, $test['output']);
    }
    
    $this->call($method, $api, $body);
  }

  /**
   * 校验数组对象。这里的数组特指以字符键值为脚标的数组。
   *
   * @param array $item
   * @param string|array $defines
   * @param bool $is_start
   *
   * @internal param $output
   */
  protected function validateArray( $item, $defines, $is_start = false ) {
    foreach ( $defines as $key => $define ) {
      if ($is_start) {
        $this->path    = array_slice( $this->path, 0, 1 );
        $this->path[1] = $key;
      } else {
        $this->path[] = $key;
      }

      $define = is_array( $define ) ? $define : [ 'type' => $define ];
      if ( $define['not_exist'] ) {
        $this->assertArrayNotHasKey( $key, $item, $this->createErrorMessage( "返回数据中不应该包含字段 {$key}" ) );
        continue;
      }
      if ( $define['not_null'] ) {
        $this->assertNotNull( $item[ $key ], $this->createErrorMessage( "返回数据不应为 null，在{$key}" ) );
      }
      $this->assertArrayHasKey( $key, $item, $this->createErrorMessage( "返回数据中应包含字段 {$key}" ) );
      $this->validateValue( $key, $item[ $key ], $define );
      if (!$is_start) {
        array_pop($this->path);
      }
    }
  }

  /**
   * 校验 `decode` 后的 JSON 对象
   *
   * @param $body
   * @param $define
   */
  protected function validateJSON( $body, $define ) {
    $this->validateArray( $body, $define, true );
  }

  /**
   * 按照 `define` 的定义校验 `value` 的值
   * 
   * @param $key
   * @param $value
   * @param array $define
   *
   * @return bool
   */
  protected function validateValue( $key, $value, array $define ) {
    //todo:在统计数据里面经常最后会有一个id=amount的总计，这个amount不能满足id、date等格式要求 by woddy
    if (ArrayUtils::isSequentialArray($define)) {
      $check = $value === $define;
      $this->assertTrue($check, $this->createErrorMessage("字段 {$key} 的值必须是数组,内容必须是 " . json_encode($define)));
      return $check;
    }
    if (!is_string($define['type'])) {
      $check = $value === $define['type'];
      $this->assertTrue($check, $this->createErrorMessage("字段 {$key} 的值必须等于 {$define['type']}"));
      return $check;
    }
    switch ($define['type']) {
      case 'string':
        $check = is_string($value);
        if ($check) {
          if (array_key_exists('not_empty', $define)) {
            $this->assertNotEmpty($value, $this->createErrorMessage("字段 {$key} 的内容不能为空"));
          }
          if ($define['maxlength']) {
            $this->assertLessThanOrEqual($define['maxlength'], strlen($value), $this->createErrorMessage("字段 {$key} 的长度不应超过 {$define['maxlength']}"));
          }
          if ($define['minlength']) {
            $this->assertGreaterThanOrEqual($define['minlength'], strlen($value), $this->createErrorMessage("字段 {$key} 的长度不应少于 {$define['minlength']}"));
          }
        }
        break;

      case 'number':
        $check = is_numeric($value);
        break;

      case 'int':
        $check = is_int($value);
        break;

      case 'uint':
        $check = is_int($value) && $value >=0;
        break;

      case 'float':
        $check = is_float($value);
        break;

      case 'array':
        $check = is_array($value) && ArrayUtils::isSequentialArray($value);
        if ($check) {
          if (array_key_exists('not_empty', $define)) {
            $this->assertGreaterThan(0, count($value), $this->createErrorMessage("字段 {$key} 的数组不能为空"));
          }
          if ( $define['item'] ) {
            foreach ( $value as $item ) {
              $this->validateArray( $item, $define['item'] );
            }
          }
        }
        break;

      case 'object': // php里面没有对象,这里的 `object` 取自 JS 里的概念
        $check = is_array($value);
        if (is_array($define) && $define['fields']) {
          $this->validateArray($value, $define['fields']);
        }
        break;

      case 'date':
        $check = preg_match(self::$dateReg, $value);
        break;

      case 'time':
        $check = preg_match(self::$timeReg, $value);
        break;

      case 'datetime':
        $check = preg_match(self::$datetimeReg, $value);
        break;

      case 'email':
        $check = filter_var($value, FILTER_VALIDATE_EMAIL);
        break;

      case 'url':
        $check = filter_var($value, FILTER_VALIDATE_URL);
        break;

      case 'ip':
        $check = filter_var($value, FILTER_VALIDATE_IP);
        break;

      case 'boolean':
        $check = is_bool($value);
        break;

      default: // 正则
        $check = preg_match('~' . $value . '~', $value);
        $check = $check || (!array_key_exists('not_empty', $define) && $value === ''); // 空字符串如果没有 `not_empty` 也认为合法
        break;
    }
    if ($check && in_array($define['type'], ['number', 'float', 'int'])) {
      if (array_key_exists('min', $define)) {
        $this->assertGreaterThanOrEqual($define['min'], $value, $this->createErrorMessage("字段 {$key} 的值不应小于 {$define['min']}"));
      }
      if (array_key_exists('max', $define)) {
        $this->assertLessThanOrEqual($define['max'], $value, $this->createErrorMessage("字段 {$key} 的值不应大于 {$define['max']}"));
      }
    }
    if (!$check && $value === null) {
      $check = true;
    }
    $check = !!$check;
    $this->assertTrue($check, $this->createErrorMessage("字段 {$key} 的值不符合定义：", $define['type']));
    return $check;
  }

  /**
   * 调用注册的方法
   *
   * @param $method
   * @param $api
   * @param $body
   */
  private function call( $method, $api, $body ) {
    $key = $this->generateHookKey( $method, $api );
    if (array_key_exists($key, $this->hookMap)) {
      call_user_func([$this, $this->hookMap[$key]], $body);
    }
  }

  /**
   * 生成错误信息,包含完整路径,以便日后查看。
   * 
   * @param $message
   * @param null $define
   *
   * @return string
   */
  protected function createErrorMessage( $message, $define = null ) {
    if ($define !== null) {
      $message .= is_string($define) ? $define : json_encode($define);
    }
    $path = $this->name . ': [ ' . implode(' / ', $this->path) . ' ] ';
    return $path . $message;
  }

  /**
   * @param $method
   * @param $api
   *
   * @return string
   */
  private function generateHookKey( $method, $api ) {
    return strtoupper( $method ) . '_' . $api;
  }

  /**
   * 注册一个方法,在完成接口调用后传入返回体
   *
   * @param string $method 调用 API 的方法,`GET`、`POST`等
   * @param string $api 路径
   * @param string $callback 方法名
   */
  protected function register( $method, $api, $callback) {
    $key = $this->generateHookKey($method, $api);
    $this->hookMap[$key] = $callback;
  }
}