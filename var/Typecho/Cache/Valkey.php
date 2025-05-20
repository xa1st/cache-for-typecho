<?php
namespace Typecho\Cache;

use Predis\Client as ValkeyClient;
use Predis\Connection\ConnectionException;
use Typecho\Cache\Exception as CacheException;

class Valkey {
    /**
     * valkey客户端实例
     * @var ValkeyClient
     */
    private $valkey;

    /**
     * 是否已连接
     * @var bool
     */
    private $connected = false;

    /**
     * 构造函数
     */
    public function __construct() {
        $this->valkey = null;
    }

    /**
     * 连接到Valkey服务器
     * 
     * 此方法负责根据提供的配置信息建立与Valkey服务器的连接。它执行以下任务：
     * 1. 检查predis/predis包是否已安装
     * 2. 如果尚未连接，尝试建立连接
     * 3. 支持完整的URI字符串（如：rediss://default:password@host:port）
     * 4. 支持分离参数的传统方式
     * 
     * @param array $config 包含连接参数的数组，支持uri或host/port/password等分离参数
     * @return void
     * @throws CacheException 如果predis包未安装或连接失败，则抛出异常
     */
    public function connect(array $config = []): void {
        // 判定是否已连接
        if ($this->connected) return;

        // 检查Predis是否可用
        if (!class_exists('Predis\Client')) {
            throw new CacheException("Predis库未安装，请通过composer安装predis/predis包");
        }

        try {
            // 连接选项
            $options = [];
            if (isset($config['timeout']) && $config['timeout'] > 0) {
                $options['timeout'] = $config['timeout'];
            }

            // 如果提供了URI，直接使用
            if (isset($config['uri']) && !empty($config['uri'])) {
                $this->valkey = new ValkeyClient($config['uri'], $options);
            } 
            // 否则构建URI
            else {
                $scheme = $config['scheme'] ?? 'redis';
                $host = $config['host'] ?? '127.0.0.1';
                $port = $config['port'] ?? 6379;
                $password = $config['password'] ?? '';
                $db = $config['db'] ?? 0;
                
                // 构建URI
                $uri = $scheme . '://';
                if (!empty($password)) {
                    $uri .= 'default:' . urlencode($password) . '@';
                }
                $uri .= $host . ':' . $port;
                if ($db > 0) {
                    $uri .= '/' . $db;
                }

                $this->valkey = new ValkeyClient($uri, $options);
            }

            // 测试连接
            $this->valkey->ping();

            // 设置为已连接
            $this->connected = true;
        } catch (ConnectionException $e) {
            $this->connected = false;
            throw new CacheException('Valkey连接失败: ' . $e->getMessage());
        } catch (\Exception $e) {
            $this->connected = false;
            throw new CacheException('Valkey连接失败: ' . $e->getMessage());
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
        
        if (!$this->connected) return $default;

        try {
            $value = $this->valkey->get($key);
            
            // 缓存不存在则返回默认值
            if ($value === null) return $default;
            
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
        if (!$this->connected) return false;

        try {
            $serializedValue = serialize($value);
            
            if ($expire > 0) {
                $result = $this->valkey->setex($key, $expire, $serializedValue);
                return $result->getPayload() === 'OK';
            }
            
            $result = $this->valkey->set($key, $serializedValue);
            return $result->getPayload() === 'OK';
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * 删除缓存
     *
     * @param string $key 缓存键
     * @return bool
     */
    public function delete($key): bool {
        if (!$this->connected) return false;

        try {
            $result = $this->valkey->del([$key]);
            return $result->getPayload() > 0;
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
        if (!$this->connected) return false;

        try {
            $result = $this->valkey->exists($key);
            return $result->getPayload() > 0;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * 清空指定前缀的缓存
     *
     * 此方法尝试清空当前数据库中指定前缀的缓存项
     * 如果操作成功，返回true；如果发生异常，返回false
     *
     * @param string $prefix 缓存键前缀
     * @return bool 清空缓存操作的结果
     */
    public function flush($prefix = '') {
        if (!$this->connected) return false;

        // 不要全部清除，必须指定前缀
        if (empty($prefix)) return false;

        try {
            $keys = $this->valkey->keys($prefix . '*');
            
            if (!empty($keys)) {
                $result = $this->valkey->del($keys);
                return $result->getPayload() > 0;
            }
            
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
    public function close(): bool {
        if ($this->connected) {
            try {
                if ($this->valkey) {
                    $this->valkey->disconnect();
                }
                $this->connected = false;
                return true;
            } catch (\Exception $e) {
                $this->connected = false;
                return false;
            }
        }
        return true;
    }

    /**
     * 获取连接状态
     *
     * @return bool
     */
    public function isConnected(): bool {
        return $this->connected;
    }

    /**
     * 获取Valkey客户端实例（用于高级操作）
     *
     * @return ValkeyClient|null
     */
    public function getClient(): ?ValkeyClient {
        return $this->valkey;
    }
}