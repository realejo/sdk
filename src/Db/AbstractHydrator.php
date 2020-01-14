<?php

namespace Realejo\Sdk\Db;

use Laminas\Hydrator\NamingStrategy\UnderscoreNamingStrategy;
use Laminas\Hydrator\Reflection;
use Laminas\Hydrator\Strategy\BooleanStrategy;
use Laminas\Hydrator\Strategy\ClosureStrategy;
use Laminas\Hydrator\Strategy\DateTimeFormatterStrategy;
use Laminas\Hydrator\Strategy\DefaultStrategy;
use Realejo\Sdk\Enum\Enum;

abstract class AbstractHydrator extends Reflection
{
    public const BOOLEAN = 'bool';
    public const STRING = 'string';
    public const INTEGER = 'integer';
    public const DATETIME = 'datetime';
    public const DATE = 'date';
    public const ENUM = 'enum';
    public const JSON_OBJECT = 'json_object';
    public const JSON_ARRAY = 'json_array';

    protected static $schema = [];

    /**
     * @var int
     */
    protected $jsonEncodeOptions = 0;

    public function __construct()
    {
        parent::__construct();

        $this->setNamingStrategy(new UnderscoreNamingStrategy());

        foreach (static::$schema as $key => $config) {
            if (isset($config['type'])) {
                switch ($config['type']) {
                    case self::BOOLEAN:
                        $this->addStrategy($this->hydrateName($key), new BooleanStrategy('1', '0'));
                        break;
                    case self::DATETIME:
                    case self::DATE:
                        $format = $config['type'] === self::DATE ? 'Y-m-d|' : 'Y-m-d H:i:s';
                        $this->addStrategy($this->hydrateName($key), new DateTimeFormatterStrategy($format));
                        break;
                    case self::ENUM:
                        $this->addStrategy(
                            $this->hydrateName($key),
                            new ClosureStrategy(
                                static function (Enum $value = null) {
                                    return $value ? $value->getValue() : null;
                                },
                                static function ($value) use ($config) {
                                    return new $config['enum']($value);
                                }
                            )
                        );
                        break;
                    case self::JSON_ARRAY:
                    case self::JSON_OBJECT:
                        $asArray = $config['type'] === self::JSON_ARRAY;
                        $jsonEncodeOptions = $this->getJsonEncodeOptions();
                        $this->addStrategy(
                            $this->hydrateName($key),
                            new ClosureStrategy(
                                static function ($value = null) use ($jsonEncodeOptions) {
                                    return $value === null ? null : json_encode($value, $jsonEncodeOptions);
                                },
                                static function ($value) use ($asArray) {
                                    return json_decode($value, $asArray);
                                }
                            )
                        );
                        unset($asArray);
                        break;
                    case self::INTEGER:
                    case self::STRING:
                    default:
                        $this->addStrategy($this->hydrateName($key), new DefaultStrategy());
                        break;
                }
            }
        }
    }

    public function getJsonEncodeOptions(): int
    {
        return $this->jsonEncodeOptions;
    }
}
