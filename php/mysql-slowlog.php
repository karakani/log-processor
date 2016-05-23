<?php
/**
 * Created by Toshihiro Karakane.
 * Date: 2016/05/21
 */

namespace LogProcessor;

require realpath(__DIR__ . DIRECTORY_SEPARATOR . 'class.php');

date_default_timezone_set('Asia/Tokyo');

// 準備を行う
$opt = getopt('', [
    'out::', // ファイル出力。未指定の場合には標準出力を使用する
    'start::', // ファイルの読込開始秒. これよりも早い場合には切り捨てられる。
    'end::', // ファイルの読込終了秒. これよりも遅いエントリを読み込んだ場合には終了する
    'nostream:', // ストリームモードを使用しない
]);

// 割り込み(Ctrl+C)時に適切な終了処理を行う。割り込み処理が行えない場合には無視する
if (function_exists('pcntl_signal')) {
    pcntl_signal(SIGINT, 'signalHandler');
}

// 標準出力の準備を行う
$out = (isset($opt['opt']) ? $opt['out'] : "php://stdout");
$start = isset($opt['start']) ? $opt['start'] : false;
$end = isset($opt['end']) ? $opt['end'] : false;
$nostream = isset($opt['nostream']) ? $opt['nostream'] : false;

$writer = new XmlWriter($out, !$nostream);
$writer->open();

if ($start and $end) {
    $writer->setTimeInfo($start, $end);
}

// 標準入力の準備を行う
$reader = new SlowLogReader('php://stdin');

while ($e = $reader->readEntry()) {

    if ($start and $e->start < $start) continue;
    if ($end and $end < $e->end) break;

    $writer->write($e);
}

$writer->close();
$reader->close();


// シグナルハンドラ
$sigCounter = 0;
function signalHandler()
{
    global $sigCounter;
    $sigCounter++;

    if ($sigCounter == 2) {
        exit;
    }

    error_log('Please do Ctrl+C again to exit.');
}


class SlowLogReader
{
    private $fp;
    private $bufferedLine = [];
    private $lastEntryTime = null;
    private $reading = false;

    public function __construct($path)
    {
        $this->fp = fopen($path, 'r');

        if ($this->fp === false) {
            throw new \Exception('File cannot be opened: ' . $path);
        }
    }

    function readEntry()
    {
        while ($line = fgets($this->fp)) {
            if ($this->reading) {
                if ($line[0] == '#') {
                    // 新しいレコードを検出した場合、エントリを出力して終了する
                    $entry = $this->getEntry();

                    $this->bufferedLine = [$line];
                    $this->reading = false;

                    if ($entry) {
                        return $entry;
                    } else {
                        continue;
                    }
                } else {
                    $this->bufferedLine[] = $line;
                }

            } else {
                if ($line[0] != '#') {
                    $this->reading = true;
                }
                $this->bufferedLine[] = $line;
            }
        }

        return $this->getEntry();
    }

    /**
     * バッファーからエントリを取得する
     */
    private function getEntry()
    {
        $entry = new Entry;

        // 1行目の入力が # ではない場合無視する
        if (empty($this->bufferedLine) or $this->bufferedLine[0][0] !== '#') {
            return null;
        }

        $params = [];
        while ($line = array_shift($this->bufferedLine)) {
            if ($line[0] != '#')
                break;

            $line = substr(trim($line), 2);
            if (preg_match('{^Time: ([0-9: ]+)$}', $line, $m)) {
                $params['Time'] = $m[1];
            } else if (preg_match('{^User@Host: (.*)$}', $line, $m)) {
                $params['User@Host'] = $m[1];
            } else {
                foreach (explode('  ', $line) as $kv) {
                    list($k, $v) = explode(': ', $kv, 2);
                    $params[$k] = $v;
                }
            }
        }

        // 残りの行は全て本文として解釈する
        $sql = $line . implode("", $this->bufferedLine);
        $params['body'] = $sql;

        // タイムスタンプを設定する
        if (isset($params['Time'])) {
            $params['Time'] = $this->parseTime($params['Time']);
            $this->lastEntryTime = $params['Time'];
        } else {
            $params['Time'] = $this->lastEntryTime;
        }

        $entry->start = $params['Time'];
        $entry->end = $params['Time'] + $params['Query_time'];
        $entry->params = $params;

        // SQLからテーブル名とStatementの種類を取得する
        if (preg_match('{SET timestamp=[0-9]+;\s*([a-zA-Z]+)\s}m', $entry->params['body'], $m)) {
            $entry->command = $m[1];
        }

        // WARN: This code may be inaccurate when a query has sub query.
        // 警告: このコードはサブクエリが含まれる場合に正しく動作しないことがあります。
        if (preg_match('{SET timestamp=[0-9]+;\s*.+(FROM|INTO|DESCRIBE)\s+`?([a-zA-Z0-9_]+)`?}', $entry->params['body'], $m)) {
            $entry->table = $m[2];
        }

        return $entry;
    }

    private function parseTime($time)
    {
        $t = strptime($time, '%y%m%d %T');
        return mktime($t['tm_hour'], $t['tm_min'], $t['tm_sec'], $t['tm_mon'] + 1, $t['tm_mday'], $t['tm_year'] + 1900);
    }

    public function close()
    {
        fclose($this->fp);
    }
}
