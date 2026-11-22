<?php
/**
 * Repeat rules, occurrence expansion and event queries.
 */

if (!defined("ABSPATH")) {
    exit();
}

/**
 * Returns the occurrences of one event overlapping [$from, $to].
 * Each occurrence: array( 'post_id', 'start' => DateTimeImmutable, 'end' => DateTimeImmutable ).
 */
function cc_get_occurrences(
    $post_id,
    DateTimeImmutable $from,
    DateTimeImmutable $to,
) {
    $tz = wp_timezone();
    $s = cc_meta($post_id, "start");
    $e = cc_meta($post_id, "end");
    if (!$s) {
        return [];
    }
    $start = new DateTimeImmutable($s, $tz);
    $end = $e ? new DateTimeImmutable($e, $tz) : $start->modify("+1 hour");
    $duration = max(0, $end->getTimestamp() - $start->getTimestamp());
    $freq = cc_meta($post_id, "freq");
    $out = [];

    $push = function (DateTimeImmutable $occ) use (
        &$out,
        $post_id,
        $duration,
        $from,
        $to,
    ) {
        $occ_end = $occ->setTimestamp($occ->getTimestamp() + $duration);
        if ($occ_end >= $from && $occ <= $to) {
            $out[] = [
                "post_id" => $post_id,
                "start" => $occ,
                "end" => $occ_end,
            ];
        }
    };

    if (!$freq) {
        $push($start);
        return $out;
    }

    // Repeats never run more than one year past the start, whatever is stored.
    $limit = cc_effective_until($start, cc_meta($post_id, "until"));
    $limit = $limit < $to ? $limit : $to;
    $interval = max(1, (int) cc_meta($post_id, "interval", 1));
    $weekdays = array_map(
        "intval",
        (array) get_post_meta($post_id, "_cc_weekdays", true),
    );
    $nth = (int) cc_meta($post_id, "nth", 1);
    $nth_day = (int) cc_meta($post_id, "nth_day", (int) $start->format("N"));

    for ($n = 0; $n < 400; $n++) {
        foreach (
            cc_rec_candidates(
                $start,
                $freq,
                $n,
                $interval,
                $weekdays,
                $nth,
                $nth_day,
            )
            as $cand
        ) {
            if ($cand < $start) {
                continue;
            }
            if ($cand > $limit) {
                break 2;
            }

            $push($cand);
        }
    }
    return $out;
}

/** All occurrences of published events in [$from, $to], optionally filtered by district/society entry ID. */
function cc_query_occurrences(
    DateTimeImmutable $from,
    DateTimeImmutable $to,
    $district = 0,
    $society = 0,
    $max_events = 500,
    $search = "",
    $type = 0,
) {
    $meta = [
        "relation" => "AND",
        [
            "key" => "_cc_start",
            "value" => $to->format("Y-m-d H:i:s"),
            "compare" => "<=",
            "type" => "DATETIME",
        ],
        [
            "key" => "_cc_last",
            "value" => $from->format("Y-m-d H:i:s"),
            "compare" => ">=",
            "type" => "DATETIME",
        ],
    ];
    if ($district) {
        $meta[] = [
            "key" => "_cc_district_id",
            "value" => (int) $district,
            "compare" => "=",
        ];
    }
    if ($society) {
        $meta[] = [
            "key" => "_cc_society_id",
            "value" => (int) $society,
            "compare" => "=",
        ];
    }
    $args = [
        "post_type" => "cc_event",
        "post_status" => "publish",
        "posts_per_page" => $max_events,
        "no_found_rows" => true,
        "fields" => "ids",
        "meta_query" => $meta,
        "cc_title_search" => $search,
    ];
    if ($type) {
        $args["tax_query"] = [
            [
                "taxonomy" => "cc_event_type",
                "field" => "term_id",
                "terms" => (int) $type,
            ],
        ];
    }
    $q = new WP_Query($args);
    $occ = [];
    foreach ($q->posts as $pid) {
        $events = cc_get_occurrences($pid, $from, $to);
        array_push($occ, ...$events);
    }
    usort($occ, function ($a, $b) {
        return $a["start"] <=> $b["start"];
    });
    return $occ;
}

/** Title-only search for WP_Query (core's "s" also searches content). Use 'cc_title_search' => 'text'. */
add_filter(
    "posts_where",
    function ($where, $query) {
        $term = $query->get("cc_title_search");
        if (is_string($term) && "" !== $term) {
            global $wpdb;
            $where .= $wpdb->prepare(
                " AND {$wpdb->posts}.post_title LIKE %s",
                "%" . $wpdb->esc_like($term) . "%",
            );
        }
        return $where;
    },
    10,
    2,
);

/**
 * Day of month of the nth ISO weekday ($wd 1-7) in a month; $nth 1-4 or -1 for the last one. 0 if none.
 */
function cc_nth_weekday_in_month($year, $month, $nth, $wd)
{
    $first_ts = gmmktime(0, 0, 0, $month, 1, $year);
    $dim = (int) gmdate("t", $first_ts);
    if (-1 === (int) $nth) {
        $last_dow = (int) gmdate("N", gmmktime(0, 0, 0, $month, $dim, $year));
        return $dim - (($last_dow - $wd + 7) % 7);
    }
    $first_dow = (int) gmdate("N", $first_ts);
    $day = 1 + (($wd - $first_dow + 7) % 7) + 7 * ((int) $nth - 1);
    return $day <= $dim ? $day : 0;
}

/** The occurrence start time(s) generated by step $n of a repeat rule (not yet limited to the event's start/end). */
function cc_rec_candidates(
    DateTimeImmutable $start,
    $freq,
    $n,
    $interval,
    array $weekdays,
    $nth,
    $nth_day,
) {
    $step = (int) $n * (int) $interval;
    $c = [];

    if ("daily" === $freq) {
        $c[] = $start->modify("+{$step} days");
    } elseif ("weekly" === $freq) {
        $weekdays = array_values(array_filter(array_map("intval", $weekdays)));
        if (!$weekdays) {
            $weekdays = [(int) $start->format("N")];
        }
        sort($weekdays);
        $h = (int) $start->format("G");
        $mi = (int) $start->format("i");
        $week_start = $start->modify(
            "-" . ((int) $start->format("N") - 1) . " days",
        );
        foreach ($weekdays as $wd) {
            $days = 7 * $step + $wd - 1;
            $c[] = $week_start->modify("+{$days} days")->setTime($h, $mi);
        }
    } elseif ("monthly" === $freq || "monthly_nth" === $freq) {
        $total = (int) $start->format("n") - 1 + $step;
        $y = (int) $start->format("Y") + intdiv($total, 12);
        $m = ($total % 12) + 1;
        if ("monthly" === $freq) {
            $sd = (int) $start->format("j");
            $day = checkdate($m, $sd, $y) ? $sd : 0; // Months without that date are skipped.
        } else {
            $day = cc_nth_weekday_in_month(
                $y,
                $m,
                $nth,
                max(1, min(7, (int) $nth_day)),
            );
        }
        if ($day) {
            $c[] = $start->setDate($y, $m, $day);
        }
    } elseif ("yearly" === $freq) {
        $y = (int) $start->format("Y") + $step;
        $sm = (int) $start->format("n");
        $sd = (int) $start->format("j");
        if (checkdate($sm, $sd, $y)) {
            $c[] = $start->setDate($y, $sm, $sd);
        }
    }
    return $c;
}

/** First date on or after $start that matches the repeat rule (for most rules, $start itself). */
function cc_align_start(
    DateTimeImmutable $start,
    $freq,
    $interval,
    array $weekdays,
    $nth,
    $nth_day,
) {
    for ($n = 0; $n < 400; $n++) {
        foreach (
            cc_rec_candidates(
                $start,
                $freq,
                $n,
                $interval,
                $weekdays,
                $nth,
                $nth_day,
            )
            as $cand
        ) {
            if ($cand >= $start) {
                return $cand;
            }
        }
    }
    return $start;
}

/**
 * Last moment a repeating event may occur: the chosen "until" date, but never more than one year after the start.
 * An empty or earlier-than-start "until" means the full year.
 */
function cc_effective_until(DateTimeImmutable $start, $until)
{
    $cap = $start->modify("+1 year")->setTime(23, 59, 59);
    if ($until && preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $until)) {
        $u = date_create_immutable($until . " 23:59:59", wp_timezone());
        if ($u && $u >= $start->setTime(0, 0)) {
            return $u < $cap ? $u : $cap;
        }
    }
    return $cap;
}
