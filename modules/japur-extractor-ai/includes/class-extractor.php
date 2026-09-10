<?php
if (!defined('ABSPATH')) exit;

/**
 * Local article extractor. No AI is used here.
 * Returns only the main article body; source title/description are never returned.
 */
class JAF_Extractor {
    public static function extract($url){
        $r=wp_safe_remote_get($url,[
            'timeout'=>45,
            'redirection'=>5,
            'user-agent'=>'Mozilla/5.0 (compatible; JapurExtractor/3.0; +'.home_url('/').')'
        ]);
        if(is_wp_error($r)) return $r;
        $code=wp_remote_retrieve_response_code($r);
        if($code<200||$code>=400) return new WP_Error('extract','Halaman gagal diambil. HTTP '.$code);
        $html=wp_remote_retrieve_body($r);
        if(!$html) return new WP_Error('extract','Konten halaman kosong.');
        if(!class_exists('DOMDocument') || !class_exists('DOMXPath')) return new WP_Error('extract','DOMDocument/DOMXPath tidak tersedia di server.');

        libxml_use_internal_errors(true);
        $dom=new DOMDocument();
        @$dom->loadHTML('<?xml encoding="utf-8" ?>'.$html);
        libxml_clear_errors();
        $xp=new DOMXPath($dom);

        // Hard-remove page chrome before candidate scoring.
        self::remove_nodes($xp,'//script|//style|//noscript|//template|//svg|//canvas|//nav|//footer|//header|//form|//iframe|//button|//input|//select|//textarea|//dialog|//*[@aria-hidden="true"]|//*[@role="navigation"]|//*[@role="banner"]|//*[@role="contentinfo"]');
        $bad_words=['cookie','advert','banner','social','share','comment','related','recommend','sidebar','breadcrumb','newsletter','trending','popular','widget','menu','login'];
        foreach($bad_words as $bad){
            $q='//*[self::div or self::section or self::aside or self::ul or self::ol][contains(translate(@class,"ABCDEFGHIJKLMNOPQRSTUVWXYZ","abcdefghijklmnopqrstuvwxyz"),"'.$bad.'") or contains(translate(@id,"ABCDEFGHIJKLMNOPQRSTUVWXYZ","abcdefghijklmnopqrstuvwxyz"),"'.$bad.'")]';
            self::remove_nodes($xp,$q);
        }

        $candidates=[];
        // Semantic containers get priority, but are still validated by content quality.
        foreach($xp->query('//article|//main') as $n) $candidates[]=[$n,self::candidate_score($n)+55];
        foreach($xp->query('//div|//section') as $n){
            $score=self::candidate_score($n);
            if($score>0) $candidates[]=[$n,$score];
        }

        // IMPORTANT: never use <body> as an article fallback. A page body commonly
        // contains navigation, login, category menus and footer content.
        usort($candidates,function($a,$b){ return $b[1]<=>$a[1]; });

        $best=null;
        $best_score=-INF;
        $best_confidence=0;
        foreach($candidates as $candidate){
            [$root,$score]=$candidate;
            $data=self::collect($xp,$root);
            if(!$data['material']) continue;

            $final=$score;
            $final+=min(80,$data['paragraphs']*5);
            $final+=min(100,(int)($data['length']/250));
            $final+=min(30,$data['headings']*4);
            $final-=min(60,(int)($data['link_density']*80));
            $final-=min(45,(int)($data['list_ratio']*45));
            if($data['paragraphs']<3) $final-=45;
            if($data['length']<400) $final-=40;
            if($data['navigation_hits']>0) $final-=min(70,$data['navigation_hits']*14);

            // Prefer a compact, article-like container over a giant ancestor.
            if($data['length']>14000 && $data['paragraphs']<8) $final-=35;
            if($data['paragraphs']>=5 && $data['length']>=700 && $data['link_density']<0.15 && $data['list_ratio']<0.35) $final+=35;

            if($final>$best_score){
                $best_score=$final;
                $best=$data;
                $best_confidence=self::confidence($data,$score,$final);
            }
        }

        if(!$best || $best['length']<400 || $best['paragraphs']<3 || $best_confidence<60){
            return new WP_Error('extract','Konten utama artikel tidak dapat dipastikan. Ekstraksi dibatalkan agar menu, navigasi, atau konten halaman lain tidak terkirim ke AI.');
        }

        return [
            'material'=>$best['material'],
            'url'=>$url,
            'length'=>$best['length'],
            'paragraphs'=>$best['paragraphs'],
            'words'=>$best['words'],
            'mode'=>'full_article',
            'confidence'=>$best_confidence,
            'source_title'=>'',
            'source_description'=>''
        ];
    }


    /**
     * Extract/clean material supplied directly by the user.
     * Plain text is normalized; HTML is parsed with the same article-quality
     * scoring used by URL extraction. No AI is used.
     */
    public static function extract_text($input){
        $input=trim((string)$input);
        if($input==='') return new WP_Error('extract','Materi kosong.');

        // Plain text: preserve paragraph boundaries and normalize whitespace.
        if(strpos($input,'<')===false || !preg_match('/<\/?[a-z][^>]*>/iu',$input)){
            $text=html_entity_decode($input,ENT_QUOTES|ENT_HTML5,'UTF-8');
            $text=str_replace(["\xC2\xA0","\xE2\x80\x8B","\xE2\x80\x8C","\xE2\x80\x8D"],' ',$text);
            $lines=preg_split('/\R/u',$text);
            $parts=[];
            foreach($lines as $line){
                $line=trim(preg_replace('/[ \t]+/u',' ',$line));
                if($line!=='') $parts[]=$line;
            }
            $text=trim(implode("\n\n",$parts));
            $words=preg_match_all('/\S+/u',$text,$m);
            if(mb_strlen($text)<30) return new WP_Error('extract','Materi terlalu pendek untuk diekstrak.');
            return ['material'=>$text,'length'=>mb_strlen($text),'paragraphs'=>count($parts),'words'=>$words?:0,'mode'=>'text'];
        }

        // HTML pasted into the material field: use the same local article extraction logic.
        if(!class_exists('DOMDocument') || !class_exists('DOMXPath')) return new WP_Error('extract','DOMDocument/DOMXPath tidak tersedia di server.');
        libxml_use_internal_errors(true);
        $dom=new DOMDocument();
        @$dom->loadHTML('<?xml encoding="utf-8" ?>'.$input);
        libxml_clear_errors();
        $xp=new DOMXPath($dom);
        self::remove_nodes($xp,'//script|//style|//noscript|//template|//svg|//canvas|//nav|//footer|//header|//form|//iframe|//button|//input|//select|//textarea|//dialog|//*[@aria-hidden="true"]|//*[@role="navigation"]|//*[@role="banner"]|//*[@role="contentinfo"]');
        $bad_words=['cookie','advert','banner','social','share','comment','related','recommend','sidebar','breadcrumb','newsletter','trending','popular','widget','menu','login'];
        foreach($bad_words as $bad){
            $q='//*[self::div or self::section or self::aside or self::ul or self::ol][contains(translate(@class,"ABCDEFGHIJKLMNOPQRSTUVWXYZ","abcdefghijklmnopqrstuvwxyz"),"'.$bad.'") or contains(translate(@id,"ABCDEFGHIJKLMNOPQRSTUVWXYZ","abcdefghijklmnopqrstuvwxyz"),"'.$bad.'")]';
            self::remove_nodes($xp,$q);
        }
        $candidates=[];
        foreach($xp->query('//article|//main') as $n) $candidates[]=[$n,self::candidate_score($n)+55];
        foreach($xp->query('//div|//section') as $n){ $score=self::candidate_score($n); if($score>0) $candidates[]=[$n,$score]; }
        usort($candidates,function($a,$b){ return $b[1]<=>$a[1]; });
        $best=null; $best_score=-INF; $best_confidence=0;
        foreach($candidates as $candidate){
            [$root,$score]=$candidate;
            $data=self::collect($xp,$root);
            if(!$data['material']) continue;
            $final=$score;
            $final+=min(80,$data['paragraphs']*5);
            $final+=min(100,(int)($data['length']/250));
            $final+=min(30,$data['headings']*4);
            $final-=min(60,(int)($data['link_density']*80));
            $final-=min(45,(int)($data['list_ratio']*45));
            if($data['paragraphs']<3) $final-=45;
            if($data['length']<400) $final-=40;
            if($data['navigation_hits']>0) $final-=min(70,$data['navigation_hits']*14);
            if($data['length']>14000 && $data['paragraphs']<8) $final-=35;
            if($data['paragraphs']>=5 && $data['length']>=700 && $data['link_density']<0.15 && $data['list_ratio']<0.35) $final+=35;
            if($final>$best_score){ $best_score=$final; $best=$data; $best_confidence=self::confidence($data,$score,$final); }
        }
        if(!$best || $best['length']<400 || $best['paragraphs']<3 || $best_confidence<60){
            return new WP_Error('extract','Konten utama dari materi HTML tidak dapat dipastikan. Ekstraksi dibatalkan agar navigasi atau konten halaman lain tidak ikut diproses.');
        }
        return ['material'=>$best['material'],'length'=>$best['length'],'paragraphs'=>$best['paragraphs'],'words'=>$best['words'],'mode'=>'html'];
    }

    private static function remove_nodes($xp,$query){
        foreach($xp->query($query) as $n){ if($n->parentNode) $n->parentNode->removeChild($n); }
    }

    private static function candidate_score($n){
        $cls=strtolower(trim((string)$n->getAttribute('class').' '. $n->getAttribute('id')));
        $text=self::clean_text($n->textContent);
        $len=mb_strlen($text);
        if($len<180) return 0;

        $p=$n->getElementsByTagName('p')->length;
        $h=$n->getElementsByTagName('h2')->length+$n->getElementsByTagName('h3')->length;
        $li=$n->getElementsByTagName('li')->length;
        $a=$n->getElementsByTagName('a')->length;
        $link_text=0;
        foreach($n->getElementsByTagName('a') as $link) $link_text+=mb_strlen(self::clean_text($link->textContent));
        $link_density=$len>0 ? ($link_text/$len) : 1;
        $list_ratio=($p+$li)>0 ? ($li/($p+$li)) : 0;

        $score=min(80,$p*8)+min(24,$h*4)+min(20,(int)($len/400));
        $strong_hints=[
            'article-content'=>35,'article__content'=>35,'article-body'=>35,'article_body'=>35,
            'read-content'=>38,'read__content'=>42,'content-body'=>34,'content__body'=>34,
            'post-content'=>32,'post-body'=>32,'entry-content'=>32,'entry-body'=>32,
            'story-content'=>32,'story-body'=>32,'detail-content'=>32,'detail__content'=>32,
            'content-article'=>32,'content__article'=>32,'article-detail'=>30,'article_detail'=>30
        ];
        foreach($strong_hints as $hint=>$bonus){ if(strpos($cls,$hint)!==false) $score+=$bonus; }
        foreach(['article','story','post','entry','detail','content','read','main-content'] as $hint){ if(strpos($cls,$hint)!==false) $score+=12; }
        foreach(['related','recommend','sidebar','comment','footer','nav','menu','advert','ads','share','social','breadcrumb','newsletter','widget','trending','popular','login','header'] as $bad){ if(strpos($cls,$bad)!==false) $score-=55; }

        $score-=min(55,(int)($link_density*90));
        $score-=min(35,(int)($list_ratio*35));
        if($p>=4) $score+=15;
        if($p>=8) $score+=15;
        if($link_density<0.12) $score+=15;
        if($list_ratio<0.20) $score+=10;
        return max(0,$score);
    }

    private static function collect($xp,$root){
        $parts=[]; $seen=[]; $headings=0; $links=0; $link_text=0; $li_count=0; $p_count=0; $navigation_hits=0;
        $nodes=$xp->query('.//h2|.//h3|.//p|.//blockquote|.//li',$root);
        foreach($nodes as $n){
            $tag=strtolower($n->nodeName);
            $text=self::clean_text($n->textContent);
            if(mb_strlen($text)<25) continue;

            if($tag==='li'){
                $li_count++;
                // Lists are allowed only as a small part of a strong article container.
                // Navigation lists are usually short, link-heavy, and repetitive.
                $anchors=$n->getElementsByTagName('a')->length;
                if($anchors>0 && mb_strlen($text)<180) { $navigation_hits++; continue; }
            } elseif($tag==='p') {
                $p_count++;
                if(self::looks_like_navigation($text)) { $navigation_hits++; continue; }
            } elseif($tag==='h2'||$tag==='h3') {
                $headings++;
                if(self::looks_like_navigation($text)) { $navigation_hits++; continue; }
            }

            $key=md5($text);
            if(isset($seen[$key])) continue;
            $seen[$key]=1;
            if($tag==='li') $parts[]='• '.$text; else $parts[]=$text;
        }

        // Some sites place article copy directly in a div. Only use those divs when
        // there are no usable paragraphs, and never as a page-wide fallback.
        if(count($parts)<3){
            foreach($xp->query('.//div',$root) as $n){
                $text=self::clean_text($n->textContent);
                if(mb_strlen($text)<120 || mb_strlen($text)>2200) continue;
                if($n->getElementsByTagName('p')->length>0) continue;
                $key=md5($text); if(isset($seen[$key])) continue;
                if(self::looks_like_navigation($text)) continue;
                $seen[$key]=1; $parts[]=$text;
            }
        }

        $material=trim(implode("\n\n",$parts));
        $material=preg_replace("/\n{3,}/u","\n\n",$material);
        $words=preg_match_all('/\S+/u',$material,$m);
        foreach($root->getElementsByTagName('a') as $a){ $links++; $link_text+=mb_strlen(self::clean_text($a->textContent)); }
        $length=mb_strlen($material);
        $link_density=$length>0 ? ($link_text/$length) : 1;
        $list_ratio=($p_count+$li_count)>0 ? ($li_count/($p_count+$li_count)) : 0;
        return [
            'material'=>$material,'length'=>$length,'paragraphs'=>count($parts),'words'=>$words?:0,
            'headings'=>$headings,'link_density'=>$link_density,'list_ratio'=>$list_ratio,
            'navigation_hits'=>$navigation_hits,'source_links'=>$links
        ];
    }

    private static function confidence($data,$base_score,$final_score){
        $c=0;
        if($data['paragraphs']>=5) $c+=20;
        elseif($data['paragraphs']>=3) $c+=12;
        if($data['length']>=700) $c+=20;
        elseif($data['length']>=400) $c+=12;
        if($data['link_density']<0.08) $c+=20;
        elseif($data['link_density']<0.15) $c+=12;
        if($data['list_ratio']<0.15) $c+=15;
        elseif($data['list_ratio']<0.30) $c+=8;
        if($data['navigation_hits']===0) $c+=15;
        elseif($data['navigation_hits']<=2) $c+=5;
        if($final_score>=130) $c+=10;
        return min(100,$c);
    }

    private static function looks_like_navigation($text){
        $t=strtolower(trim(preg_replace('/\s+/u',' ',$text)));
        if($t==='') return true;
        $patterns=[
            '/^(login|log in|masuk|daftar|gabung|sign in|sign up)\b/u',
            '/^(news|nasional|global|megapolitan|regional|tekno|otomotif|bola|lifestyle|travel|food|health)\b(?:\s*[-|•>]\s*|$)/u',
            '/^(home|beranda|menu|search|cari|subscribe|newsletter)\b/u',
            '/\b(kompas\.com\+|konten yang disimpan)\b/u'
        ];
        foreach($patterns as $p) if(preg_match($p,$t)) return true;
        return false;
    }

    private static function clean_text($text){
        $text=html_entity_decode((string)$text,ENT_QUOTES|ENT_HTML5,'UTF-8');
        $text=str_replace(["\xC2\xA0","\xE2\x80\x8B","\xE2\x80\x8C","\xE2\x80\x8D"],' ',$text);
        $text=preg_replace('/[\t\r\n ]+/u',' ',$text);
        return trim($text);
    }
}
