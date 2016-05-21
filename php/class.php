<?php

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

interface Writer
{
    public function open();

    public function close();

    public function write(Entry $e);
}

abstract class FileWriter implements Writer
{
    private $path;
    protected $fp = null;
    /**
     * ファイルのストリーム出力機能。
     * この機能を使用する場合、統計情報が記録されたヘッダーやフッターを出力しない
     */
    protected $stream;
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
