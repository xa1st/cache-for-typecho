<?php
namespace Typecho\Cache;

use Redis as RedisClient;
use Typecho\Cache\Exception as CacheException;

class Redis { 
    /**
     * redis实例
     */
    private $redis;

    /**
     * 是否已连接
     * @var bool
     */
    private $connected = false;

    /**
     * 构造函数
     */
    public function __construct() {
        $this->redis = new RedisClient();
    }

    /**
     * 连接到Redis服务器
     * 
     * 此方法负责根据提供的配置信息建立与Redis服务器的连接它执行以下任务：
     * 1. 检查PHP的redis扩展是否已加载
     * 2. 如果尚未连接，尝试建立连接
     * 3. 进行身份验证（如果配置了认证信息）
     * 4. 选择特定的数据库（如果配置了数据库索引）
     * 
     * @param array $config 包含连接参数的数组，如host、port、timeout、auth和db
     * @return void
     * @throws Exception 如果redis扩展未安装，认证失败，或连接失败，则抛出异常
     */
    public function connect(array $config = []): void {
        // 判定是否已连接
        if ($this->connected) return;
        try {
            // 连接Redis
            $result = $this->redis->connect($config['host'], $config['port'], $config['timeout'] ?? 0);
            // 是否连接成功
            if (!$result) throw new CacheException("无法连接到Redis服务器");
            // 认证
            if (!empty($config['password'])) {
                if (!$this->redis->auth($config['password'])) throw new CacheException("Redis认证失败");
            }
            // 选择数据库
            if (isset($config['db']) && $config['db'] > 0) $this->redis->select($config['db']);
            // 设置为已连接
            $this->connected = true;
        } catch (\Exception $e) {
            $this->connected = false;
            throw new CacheException('Redis连接失败: ' . $e->getMessage());
        }
    }

    /**
     * 获取缓存
     *
     * @param string $key 缓存键名
     * @param mixed $default 默认值
     * @return mixed
    */
    public function get($key, $default = null) {
        if (empty($key)) return $default;
        try {
            $value = $this->redis->get($key);
            // 缓存不存在则返回默认值
            if ($value === false || $value === null) return $default;        
            // 特殊处理序列化的布尔值false
            if ($value === 'b:0;') return false;    
            // 尝试反序列化
            $result = unserialize($value);
            // 反序列化失败则返回默认值
            return ($result !== false) ? $result : $default;
        } catch (\Exception $e) {
            return $default;
        }
    }

    /**
     * 设置缓存
     *
     * @param string $key 缓存键
     * @param mixed $value 缓存值
     * @param int $expire 过期时间（秒）
     * @return bool
     */
    public function set(string $key, mixed $value, int $expire = 0): bool {
        if ($expire > 0) return $this->redis->setex($key, $expire, serialize($value));       
        return $this->redis->set($key, serialize($value));
    }

    /**
    * 删除缓存
    *
    * @access public
    * @param string $key 缓存键
    * @return bool
    */
    public function delete($key): bool {
        try {
             return (bool)$this->redis->del($key);
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * 检查缓存是否存在
     *
     * @param string $key 缓存键
     * @return bool
     */
    public function has(string $key): bool {
        return (bool)$this->redis->exists($key);
    }

    /**
     * 清空所有缓存
     *
     * 此方法尝试清空当前数据库中的所有缓存项
     * 如果操作成功，返回true；如果发生异常，返回false
     *
     * @return bool 清空缓存操作的结果
    */
    public function flush($prefix = '') {
        // 不要全部清除
        if (empty($prefix)) return false;
        try {
            $keys = $this->redis->keys($prefix . '*');
            if (!empty($keys))  return (bool)$this->redis->del($keys);
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * 关闭连接
     *
     * @return bool
     */
    public function close(): bool
    {
        if ($this->connected) {
            $this->connected = false;
            return $this->redis->close();
        }
        return true;
    }
}
