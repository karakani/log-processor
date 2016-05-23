<?php

namespace LogProcessor;

interface Writer
{
    public function open();

    public function close();

    public function write(Entry $e);
}

/**
 * Created by Toshihiro Karakane.
 * Date: 2016/05/21
 *
 * @property array params miscellaneous params
 */
class Entry
{
    /** @var int required. */
    public $start;
    /** @var int optional. */
    public $end;
    /** @var string Title */
    public $title;
}

abstract class FileWriter implements Writer
{
    protected $fp = null;
    /**
     * ファイルのストリーム出力機能。
     * この機能を使用する場合、統計情報が記録されたヘッダーやフッターを出力しない
     */
    protected $stream;
    protected $begin;
    protected $end;
    private $path;
    private $requireLock;

    public function __construct($filepath, $stream = true, $requireLock = true)
    {
        $this->path = $filepath;
        if (strpos($filepath, 'php://') === 0) {
            $this->requireLock = false;
        } else {
            $this->requireLock = $requireLock;
        }
        $this->stream = $stream;
    }

    public function setTimeInfo($begin, $end)
    {
        $this->begin = $begin;
        $this->end = $end;
    }

    public function open()
    {
        if ($this->fp === null) {
            $this->fp = fopen($this->path, 'w');
            if ($this->fp === false) {
                throw new \Exception('File cannot be opened.');
            }

            if ($this->requireLock) {
                if (!flock($this->fp, LOCK_EX)) {
                    fclose($this->path);
                    throw new \Exception('File cannot be locked!');
                }
            }
        }
    }

    public function close()
    {
        fflush($this->fp);

        if ($this->requireLock) {
            flock($this->fp, LOCK_UN);
        }

        fclose($this->fp);
        $this->fp = null;
    }
}

class XmlWriter extends FileWriter
{
    public function open()
    {
        parent::open();

        fwrite($this->fp, "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n");
        fwrite($this->fp, "<?xml-stylesheet href=\"style.xsl\" type=\"application/xslt+xml\"?>");
        fwrite($this->fp, '<archive><logs>');
    }

    public function write(Entry $e)
    {
        fwrite($this->fp, $this->createLine($e) . "\r\n");
    }

    private function createLine(Entry $e)
    {
        return self::createNode('log', (array)$e, ['body']);
    }

    static private function createNode($name, array $e, array $textNodeKeys = [], $useCDATA = true)
    {
        $data = (array)$e;

        // 非効率なコードのため修正したほうがいい
        $attr = self::createAttrString($data, $textNodeKeys);
        $child = self::createChildNode($data, $textNodeKeys);
        $text = self::findTextNode($data, $textNodeKeys, $useCDATA);

        if ($child) {
            return '<' . $name . ' ' . $attr . '>' . $child . $text . '</' . $name . '>';
        } else if ($text) {
            return '<' . $name . ' ' . $attr . '>' . $text . '</' . $name . '>';
        } else {
            return '<' . $name . ' ' . $attr . '/>';
        }
    }

    static private function createAttrString(array $e, array $textNodeKeys = [])
    {
        $arr = [];
        foreach ($e as $k => $v) {
            if (!is_null($v) and !is_scalar($v)) continue;
            if (in_array($k, $textNodeKeys)) continue;
            $arr[] = escape_attr_name($k) . '="' . escape($v) . '"';
        }
        return implode(' ', $arr);
    }

    static private function createChildNode(array $e, array $textNodeKeys = [])
    {
        $string = '';
        foreach ($e as $k => $v) {
            if (!is_object($v) and !is_array($v)) continue;
            $string .= self::createNode($k, (array)$v, $textNodeKeys);
        }
        return $string ? $string : false;
    }

    static private function findTextNode(array $e, array $textNodeKeys = [], $useCDATA = true)
    {
        foreach ($e as $k => $v) {
            if (in_array($k, $textNodeKeys)) {
                if ($useCDATA) {
                    $v = strtr($v, [']]>' => ']]&gt;']);
                    return '<![CDATA[' . $v . ']]>';
                } else
                    return escape($v);
            }
        }
        return '';
    }

    public function close()
    {
        fwrite($this->fp, '</logs>');

        // メタ情報の書き出し
        if ($this->begin and $this->end) {
            fwrite($this->fp, self::createNode('meta', [
                'begin' => $this->begin,
                'end' => $this->end,
            ]));
        }

        fwrite($this->fp, '</archive>');

        parent::close();
    }
}

class JsonWriter extends FileWriter
{
    private $count = 0;

    public function open()
    {
        parent::open();

        if (!$this->stream) {
            fwrite($this->fp, '{"entries":[');
        }
    }

    public function write(Entry $e)
    {
        if (!$this->stream) {
            if ($this->count) {
                fwrite($this->fp, ",\r\n");
            } else {
                fwrite($this->fp, "\r\n");
            }
        }

        $this->count++;
        fwrite($this->fp, json_encode([
            'start' => $e->start,
            'end' => $e->end,
            'title' => $e->title,
            'params' => $e->params,
        ]));

        if ($this->stream) {
            fwrite($this->fp, "\r\n");
        }
    }

    public function close()
    {
        if (!$this->stream) {
            fwrite($this->fp, ']}');
        }

        parent::close();
    }
}

function escape($str)
{
    return htmlspecialchars($str, ENT_XML1);
}

function escape_attr_name($str)
{
    return preg_replace('{[^:A-Za-z_0-9.-]}', '-', $str);
}
