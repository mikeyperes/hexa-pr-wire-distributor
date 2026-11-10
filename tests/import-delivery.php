<?php
namespace hpr_distributor\Media {
final class ExternalImageSizing {
    public static function sync_remote_featured_image($id, $url, $title): array {
        if (str_contains($url, 'fail')) { throw new \RuntimeException('Image service unavailable'); }
        return ['updated'=>true, 'image_url'=>$url, 'attachment_id'=>900];
    }
}
}
namespace hpr_distributor\Content { final class PressReleaseLoopExclusion { const PLACED_META = '_placed'; } }
namespace {
define('ABSPATH', __DIR__);
$GLOBALS['posts']=[]; $GLOBALS['meta']=[]; $GLOBALS['hooks']=[];
function add_filter($tag,$fn,$priority=10,$args=1){$GLOBALS['hooks'][$tag][$priority][]=$fn;}
function remove_filter($tag,$fn,$priority=10){foreach($GLOBALS['hooks'][$tag][$priority]??[] as $i=>$f){if($f===$fn)unset($GLOBALS['hooks'][$tag][$priority][$i]);}}
function apply_filters($tag,$value,...$args){$hs=$GLOBALS['hooks'][$tag]??[];ksort($hs);foreach($hs as $rows)foreach($rows as $fn)$value=$fn($value,...$args);return $value;}
function get_posts($q){$ids=[];foreach($GLOBALS['posts'] as $id=>$p){if(($GLOBALS['meta'][$id][$q['meta_key']]??null)===$q['meta_value'])$ids[]=$id;}return $ids;}
function get_post($id){return isset($GLOBALS['posts'][$id])?(object)$GLOBALS['posts'][$id]:null;}
function get_post_status($id){return $GLOBALS['posts'][$id]['post_status']??false;}
function get_post_meta($id,$key,$single=true){return $GLOBALS['meta'][$id][$key]??'';}
function update_post_meta($id,$key,$value){$GLOBALS['meta'][$id][$key]=$value;}
function delete_post_meta($id,$key){unset($GLOBALS['meta'][$id][$key]);}
function wp_slash($data){return $data;}
function wp_insert_post($data,$error=true){
 if(!empty($GLOBALS['save_error']))throw new \RuntimeException('Database unavailable');
 $id=$data['ID']??(count($GLOBALS['posts'])+1);
 $types=apply_filters('smpi_article_feature_post_types',['post','press-release'],'featured_image_required');
 $base=apply_filters('option_hws_required_featured_image_post_types',['post','press-release']);
 if(in_array($data['post_type'],$types,true)||in_array($data['post_type'],$base,true))$data['post_status']='draft';
 if(!empty($GLOBALS['status_rejected']))$data['post_status']='draft';
 $GLOBALS['posts'][$id]=$data+['ID'=>$id];return $id;
}
function wp_update_post($data,$error=true){return wp_insert_post($data,$error);}
function clean_post_cache($id){}
function is_wp_error($v){return false;}
function absint($v){return abs((int)$v);}
function get_user_by($field,$id){return (object)['ID'=>$id];}
function get_date_from_gmt($d){return $d;}
function current_time($format,$gmt=false){return '2026-11-10 17:00:00';}
function sanitize_title($s){return $s;}
function term_exists($slug,$tax){return ['term_id'=>1];}
function wp_set_object_terms($id,$terms,$tax,$append){}
function get_permalink($id){return 'https://publication.example/release/'.$id;}
require __DIR__.'/../src/Import/ImportedPostWriter.php';
require __DIR__.'/../src/Import/NativeFeedImporter.php';
use hpr_distributor\Import\NativeFeedImporter;
use hpr_distributor\Import\ImportedPostWriter;
$checks=0;
function expect($yes,$label){global $checks;if(!$yes)throw new \RuntimeException($label);$checks++;echo "PASS $label\n";}
function item($n,$image=''){return ['title'=>'Release '.$n,'content'=>'Body','excerpt'=>'Excerpt','source_identity'=>'source:'.$n,'source_id'=>'post:'.$n,'source_url'=>'https://source.example/'.$n,'source_slug'=>'release-'.$n,'guid'=>'source:'.$n,'published_gmt'=>'2026-11-09 18:56:58','featured_image'=>$image];}
$settings=['post_status'=>'publish','author_id'=>7,'update_existing'=>true,'category'=>'press-release','feed_url'=>'https://source.example/feed'];
$a=NativeFeedImporter::import_item(item(1),$settings);
expect(get_post_status($a['destination_post_id'])==='publish','Missing image still publishes');
expect($a['image_warning']!=='' && $a['destination_status']==='publish','Missing image warning and actual status retained');
$b=NativeFeedImporter::import_item(item(2,'https://source.example/fail.png'),$settings);
expect(get_post_status($b['destination_post_id'])==='publish','Image exception does not block publication');
expect(str_contains($b['image_warning'],'Image service unavailable'),'Image failure recorded separately');
$c=NativeFeedImporter::import_item(item(3,'https://source.example/good.png'),$settings);
expect($c['image_attachment_id']===900 && $c['image_warning']==='','Working image preserved');
expect(count($GLOBALS['posts'])===3,'Later article continues after failed image');
$id=$a['destination_post_id'];$GLOBALS['posts'][$id]['post_status']='draft';
$d=NativeFeedImporter::import_item(item(1),$settings);
expect($d['destination_post_id']===$id && count($GLOBALS['posts'])===3 && get_post_status($id)==='publish','Unchanged-content retry repairs draft without duplicate');
$repeat=NativeFeedImporter::import_item(item(1),$settings);
expect($repeat['action']==='unchanged','Repeated delivery remains idempotent');
$draft=$settings;$draft['post_status']='draft';
$e=NativeFeedImporter::import_item(item(4),$draft);
expect(get_post_status($e['destination_post_id'])==='draft','Intentionally requested draft stays draft');
expect(apply_filters('smpi_article_feature_post_types',['post','press-release'],'featured_image_required')===['post','press-release'],'Editorial image requirements restored after delivery');
$GLOBALS['save_error']=true;
try {ImportedPostWriter::save(['post_type'=>'press-release','post_status'=>'publish']);throw new \LogicException('Expected failure');}catch(\RuntimeException $x){expect($x->getMessage()==='Database unavailable','Core save errors remain blocking');}
$GLOBALS['save_error']=false;
expect(apply_filters('option_hws_required_featured_image_post_types',['post','press-release'])===['post','press-release'],'Image policy restored even after exception');
$GLOBALS['status_rejected']=true;
try {ImportedPostWriter::save(['post_type'=>'press-release','post_status'=>'publish']);throw new \LogicException('Expected status failure');}catch(\RuntimeException $x){expect(str_contains($x->getMessage(),'requested publication status'),'Silent status rejection cannot report success');}
$GLOBALS['status_rejected']=false;
$compact=NativeFeedImporter::compact_result(['items'=>[$b],'items_processed'=>1]);
expect($compact['items'][0]['image_warning']===$b['image_warning'] && $compact['items'][0]['destination_status']==='publish','Import history preserves image warning and publication status');
echo "$checks delivery regression checks passed.\n";
}
