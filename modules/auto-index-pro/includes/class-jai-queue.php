<?php
if (!defined('ABSPATH')) exit;
class JAI_Pro_Queue {
    public static function init(){ add_action('jai_pro_process_queue',[__CLASS__,'process']); }
    public static function schedule(){ if(!wp_next_scheduled('jai_pro_process_queue')) wp_schedule_event(time()+60,'jai_pro_5min','jai_pro_process_queue'); }
    public static function unschedule(){ $t=wp_next_scheduled('jai_pro_process_queue'); if($t) wp_unschedule_event($t,'jai_pro_process_queue'); }
    public static function process(){ if(class_exists('JapurSuite_Core') && !JapurSuite_Core::module_enabled('index')) return; if(!get_option('jai_pro_enabled',1)) return; $items=JAI_Pro_DB::pending((int)get_option('jai_pro_batch',5)); foreach($items as $item){ JAI_Pro_DB::mark($item->id,'processing',$item->attempts); $res=JAI_Pro_IndexNow::submit($item->url); JAI_Pro_DB::log($item->url,$item->post_id,'IndexNow',$res['ok']?'success':'failed',$res['code'],$res['message']); if($res['ok']){ JAI_Pro_DB::mark($item->id,'success',$item->attempts,''); } else { $attempt=$item->attempts+1; if($attempt >= (int)get_option('jai_pro_max_attempts',5)) JAI_Pro_DB::mark($item->id,'failed',$attempt,$res['message']); else JAI_Pro_DB::retry($item->id,$attempt,$res['message']); } } }
}
