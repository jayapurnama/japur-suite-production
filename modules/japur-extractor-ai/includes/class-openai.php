<?php
if(!defined('ABSPATH')) exit;
class JAF_OpenAI {
 private $key;
 function __construct($key){$this->key=trim($key);}
 private function request($url,$body,$timeout=240){
  $r=wp_remote_post($url,['timeout'=>$timeout,'headers'=>['Authorization'=>'Bearer '.$this->key,'Content-Type'=>'application/json'],'body'=>wp_json_encode($body)]);
  if(is_wp_error($r))return new WP_Error('openai_http',$r->get_error_message());
  $http=wp_remote_retrieve_response_code($r); $d=json_decode(wp_remote_retrieve_body($r),true);
  if($http<200||$http>=300)return new WP_Error('openai_api',$d['error']['message']??'OpenAI API error.',['status'=>$http]);
  return $d;
 }
 function test($model){$d=$this->request('https://api.openai.com/v1/responses',['model'=>$model,'input'=>'Balas hanya OK','max_output_tokens'=>32],60);if(is_wp_error($d))return $d;return 'OK';}
 function article($prompt,$model,$max_output_tokens=7000){
  $schema=['type'=>'object','additionalProperties'=>false,'properties'=>[
   'title'=>['type'=>'string'],'description'=>['type'=>'string'],'focus_keyword'=>['type'=>'string'],
   'content_html'=>['type'=>'string'],'excerpt'=>['type'=>'string'],'slug'=>['type'=>'string'],
   'image_prompt'=>['type'=>'string']
  ],'required'=>['title','description','focus_keyword','content_html','excerpt','slug','image_prompt']];
  $d=$this->request('https://api.openai.com/v1/responses',['model'=>$model,'input'=>$prompt,'text'=>['format'=>['type'=>'json_schema','name'=>'japur_article','strict'=>true,'schema'=>$schema]],'max_output_tokens'=>max(256,min(7000,(int)$max_output_tokens))]);
  if(is_wp_error($d))return $d;$out='';
  foreach(($d['output']??[]) as $item)foreach(($item['content']??[]) as $c)if(isset($c['text']))$out.=$c['text'];
  $json=json_decode(trim($out),true);if(!is_array($json))return new WP_Error('openai_json','Respons artikel tidak valid.');
  return ['data'=>$json,'usage'=>$d['usage']??[],'response_id'=>(string)($d['id']??'')];
 }
 function title($prompt,$model,$max_output_tokens=120){
  $schema=['type'=>'object','additionalProperties'=>false,'properties'=>['title'=>['type'=>'string']],'required'=>['title']];
  $d=$this->request('https://api.openai.com/v1/responses',['model'=>$model,'input'=>$prompt,'text'=>['format'=>['type'=>'json_schema','name'=>'japur_article_title','strict'=>true,'schema'=>$schema]],'max_output_tokens'=>max(64,min(256,(int)$max_output_tokens))]);
  if(is_wp_error($d))return $d; $out='';
  foreach(($d['output']??[]) as $item) foreach(($item['content']??[]) as $c) if(isset($c['text'])) $out.=$c['text'];
  $json=json_decode(trim($out),true);
  if(!is_array($json) || empty($json['title'])) return new WP_Error('openai_title_json','Respons judul tidak valid.');
  return ['data'=>$json,'usage'=>$d['usage']??[],'response_id'=>(string)($d['id']??'')];
 }
 function image($prompt,$model,$quality='auto'){
  // Keep the existing landscape 1536x1024 output. When Cost Saver is enabled,
  // the caller can request low quality to reduce image-generation token usage.
  $body=['model'=>$model,'prompt'=>$prompt,'size'=>'1536x1024'];
  if(in_array($quality,['low','medium','high','auto'],true)) $body['quality']=$quality;
  $d=$this->request('https://api.openai.com/v1/images/generations',$body,150);
  if(is_wp_error($d))return $d;
  if(!empty($d['data'][0]['b64_json']))return ['type'=>'base64','value'=>$d['data'][0]['b64_json'],'usage'=>$d['usage']??[],'response_id'=>(string)($d['id']??'')];
  if(!empty($d['data'][0]['url']))return ['type'=>'url','value'=>$d['data'][0]['url'],'usage'=>$d['usage']??[],'response_id'=>(string)($d['id']??'')];
  return new WP_Error('openai_image','OpenAI tidak mengembalikan gambar.');
 }
}
