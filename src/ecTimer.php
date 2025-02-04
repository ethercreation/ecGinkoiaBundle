<?php
namespace bundles\ecGinkoiaBundle\src;

/**
 * 
 */
class ecTimer
{
    /**
     * @var bundles\ecGinkoiaBundle\src\ecTimer
     */
    private static $instance;
    /**
     * @var array
     */
    private $times = [];
    
    /**
     * @return self
     */
    private function __construct()
    {
        return $this;
    }
    
    /**
     * @return self
     */
    public static function get()
    {
        if (!isset(self::$instance)) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * obtain the whole timeline
     * @return array of execution times
     */
    public function getTimeLine()
    {
        $time_line = [];
        if ($this->times) {
            foreach ($this->times as $key => $tab) {
                if (isset($tab['total'])) {
                    $time_line[$key] = round($tab['total'], 3);
                }
            }
        }

        $time_line['Grand Total'] = round(array_sum($time_line), 3);

        return $time_line;
    }

    /**
     * start/restart the timer
     * @param string $timer_key
     */
    public function start(string $timer_key)
    {
        $this->times[$timer_key]['start'] = microtime(true);
    }

    /**
     * stop/pause the timer
     * @param string $timer_key
     * @return float total time
     */
    public function stop(string $timer_key)
    {
        if (isset($this->times[$timer_key]['start'])) {
            $this->times[$timer_key]['total'] = ($this->times[$timer_key]['total'] ?? 0) + (microtime(true) - $this->times[$timer_key]['start']);
        } else {
            $this->times[$timer_key]['total'] = 0;
        }

        return $this->times[$timer_key]['total'];
    }
}
