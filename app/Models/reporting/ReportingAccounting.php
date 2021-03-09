<?php

namespace App\Models\reporting;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Payment_schedule;
class ReportingAccounting extends Model
{
    use HasFactory;
    protected static $date_filter_field = 'created';

    public static function getQuery($option = null) {
        $interval = strtolower($option);
        switch ($interval) {
            case 'minute':
                $interval = "MINUTE";
                break;
            case 'day':
                $interval = "DAY";
                break;
            case 'month':
                $interval = 'MONTHNAME';
                 break;
            case 'year':
                $interval = 'YEAR';
                break;
            case 'weekday':
                $interval = 'WEEKDAY';
                break;
            case 'week':
                $interval = 'WEEKDAY';
                break;
            case 'hour':
                $interval = 'HOUR';
                break;
            default:
                $interval = "MINUTE";
                break;
        }


        $query = Payment_schedule::
            selectRaw($interval . '(created) as grpTime, SUM(amount) as amount_on_interval, COUNT(*) as payment_count')
                ->groupBy('grpTime');
        return $query;
    }


    /**
     *
     * @param type $interval MINUTE|DAY|MONTHNAME|YEAR|WEEKDAY|WEEK
     * @param int/array $status
     * @deprecated since version number
     */
    // problem setPeriodFilter function not exist
    public static function getPaymentsByStatusAndInterval($interval = 'minute', $status = 3, DateTime $period_start = null, DateTime $period_end = null) {
        if (!$period_start)
            $period_start = new DateTime('2000-01-01');
        if (!$period_end)
            $period_end = new DateTime('now');


        $q = self::getQuery($interval);
        $q = self::setPeriodFilter($q, $period_start, $period_end);

        if (is_array($status)) {
            $q->whereIn('status_id', $status);
        } else {
            $q->where("status_id", $status);
        }
        return static::buildTableResults(getPaymentsByStatusAndIntervalData($interval,$status,$period_start,$period_end));
    }
    
    public static function getPaymentsByStatusAndIntervalData($interval = 'minute', $status = 3, DateTime $period_start = null, DateTime $period_end = null) {
        if (!$period_start)
            $period_start = new DateTime('2000-01-01');
        if (!$period_end)
            $period_end = new DateTime('now');


        $q = self::getQuery($interval);
        if (is_array($status)) {
            $q->whereIn('status_id', $status);
        } else {
            $q->where("status_id", $status);
        }
        $q = self::setPeriodFilter($q, $period_start, $period_end);
        return $q;
    }

    public static function buildTableResults($query) {
        $result = array();
        $result = $query->get()->toArray();

        return $result;
    }

}
