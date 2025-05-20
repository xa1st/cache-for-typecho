<?php
namespace Typecho;

use Typecho\Cache\Exception as CacheException;

/**
 * 缓存类
 *
 */
class Cache
{
    /**
     * 适配器名称
     *
     * @access private
     * @var string
     */
    private $adapterName;

    /**
     * 数据库适配器
     * @var Adapter
     */
    private $adapter;

    /**
     * 默认配置
     *
     * @var array
     */
    private $config;

    /**
     * 前缀
     *
     * @access private
     * @var string
     */
    private $prefix;

    /**
     * 实例化的缓存对象
     * @var Cache
     */
    private static $instance;

    /**
     * 缓存连接状态
     * @var boolean
    */
    private $connected;
    
    /**
     * 缓存类构造函数
     *
     * @param mixed $adapterName 适配器名称
     * @param string $prefix 前缀
     * @param array $config 配置
     * @throws CacheException
     */
    public function __construct($adapterName = 'Redis', array $config = []) {
        // 判定缓存配置是否存在
        if (empty($config)) throw new CacheException(_t('请填写缓存配置'));
        // 全局配置
        $this->config = $config;
        // 缓存适配器名称
        $this->adapterName = $adapterName;
        // 如果是Redis缓存适配器，并且没有安装Redis扩展，则抛出异常
        if ($this->adapterName == 'Redis' && !extension_loaded('redis')) throw new CacheException(_t('当前环境不支持REDIS扩展，无法使用Redis缓存'));
        // 缓存配置
        $adapterClass = '\Typecho\Cache\\' . str_replace('_', '\\', $adapterName);

        // 适配器不存在则直接抛出错误 
        if (!class_exists($adapterClass)) throw new CacheException(_t('缓存适配器不存在'));
        // 缓存前端
        $this->prefix = $config['prefix'] ?? 'typecho_';
        //实例化适配器对象
        $this->adapter = new $adapterClass();
        // 连接到服务器
        try {
            $this->adapter->connect($config);
            $this->connected = true;
        } catch (\Throwable $e) {
            throw new CacheException(_t('连接缓存服务器失败: ' . $e->getMessage()));
        }
    }

    /**
     * 设置默认缓存对象
     *
     * @access public
     * @param Cache $cache 缓存对象
     * @return void
     */
    public static function setCache(Cache $cache): void {
        self::$instance = $cache;
    }

    /**
     * 获取默认缓存对象
     *
     * @access public
     * @return Cache
     */
    public static function getCache(): Cache {
        if (empty(self::$instance)) throw new CacheException('Cache is not initialized');
        return self::$instance;
    }

    /**
     * @return Adapter
     */
    public function getAdapter(): Adapter {
        return $this->adapter;
    }

    /**
     * 获取适配器名称
     *
     * @access public
     * @return string
     */
    public function getAdapterName(): string {
        return $this->adapterName;
    }

    /**
     * 获取前缀
     *
     * @access public
     * @return string
     */
    public function getPrefix(): string {
        return $this->prefix;
    }

    /**
     * 读取缓存
     *
     * @access public
     * @param string $key 缓存键值
     * @return mixed
     */
    public function get(string $key): mixed {
        return $this->adapter->get($this->prefix . $key);
    }

    /**
     * 写入缓存
     *
     * @access public
     * @param string $key 缓存键值
     * @param mixed $value 缓存值
     * @param int $expire 过期时间, 默认0
     * @return bool
     */
    public function set(string $key, mixed $value, int $expire = 0): bool {
        return $this->adapter->set($this->prefix . $key, $value, $expire);
    }

    /**
     * 删除缓存
     *
     * @access public
     * @param string $key 缓存键值
     * @return bool
     */
    public function delete(string $key): bool {
        return $this->adapter->delete($this->prefix . $key);
    }

    /**
     * 检查缓存是否存在
     *
     * @access public
     * @param string $key 缓存键值
     * @return bool
     */
    public function has(string $key): bool {
        return $this->adapter->has($this->prefix . $key);
    }

    /**
     * 清空缓存
     *
     * @access public
     * @return bool
     */
    public function flush(): bool {
        return $this->adapter->flush($this->prefix);
    }

    /**
     * 析构函数，用于释放资源
     * 当对象被销毁时，自动调用此方法
     * 主要作用是关闭释放缓存，释放资源
     */
    public function __destruct() {
        // 关闭连接
        $this->adapter->close();
        // 状态改为未连接
        $this->connected = false;
    }
}