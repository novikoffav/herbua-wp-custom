<?php
/**
 * Plugin Name: Collector Portraits Slider
 * Description: [collector_portraits_slider] — continuously moving horizontal slider of ACF "portrait" images, excluding avatar placeholders.
 * Version:     1.3.0
 * Author:      Andriy Novikov
 * License: GPL-3.0
 */

if (!defined('ABSPATH')) exit;

/*--------------------------------------------------------------
# 1. Optional fixed image size (cropped)
--------------------------------------------------------------*/
add_action('after_setup_theme', function () {
    if (function_exists('add_image_size')) {
        add_image_size('collector_portrait_thumb', 400, 500, true); // 4:5 crop
    }
});

/*--------------------------------------------------------------
# 2. Enqueue Swiper
--------------------------------------------------------------*/
add_action('wp_enqueue_scripts', function () {
    if (!is_singular()) return;
    global $post;
    if (!isset($post->post_content)) return;
    if (!has_shortcode($post->post_content, 'collector_portraits_slider')) return;

    wp_enqueue_style('swiper-css','https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css',[], '11.0.0');
    wp_enqueue_script('swiper-js','https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js',[], '11.0.0', true);

    wp_add_inline_style('swiper-css', <<<CSS
      .cps-wrap { position:relative; }
      .cps-wrap .swiper { width:100%; }
      .cps-wrap .swiper-wrapper { transition-timing-function:linear!important; }
      .cps-wrap .swiper-slide {
        width:var(--cps-width,200px)!important;
        display:flex;align-items:center;justify-content:center;
      }
      .cps-frame {
        width:100%;aspect-ratio:var(--cps-ratio,4/5);
        overflow:hidden;border-radius:var(--cps-radius,12px);
        background:#f6f6f6;box-shadow:0 4px 12px rgba(0,0,0,.08);
        position:relative;
      }
      .cps-frame img {
        position:absolute;inset:0;width:100%;height:100%;
        object-fit:cover;object-position:center;
      }
    CSS);

    wp_add_inline_script('swiper-js', <<<JS
      document.addEventListener('DOMContentLoaded',function(){
        document.querySelectorAll('.cps-wrap .swiper').forEach(function(node){
          const wrap=node.closest('.cps-wrap');
          const autoplay=(wrap.dataset.autoplay==='yes');
          const speed=Number(wrap.dataset.speed)||8000;
          const pause=(wrap.dataset.pause==='yes');
          const reverse=(wrap.dataset.reverse==='yes');
          new Swiper(node,{
            slidesPerView:'auto',
            spaceBetween:16,
            loop:true,
            loopAdditionalSlides:40,
            speed:speed,
            autoplay:autoplay?{
              delay:0,
              disableOnInteraction:false,
              pauseOnMouseEnter:pause,
              reverseDirection:reverse
            }:false,
            allowTouchMove:true,
            watchOverflow:true
          });
        });
      });
    JS);
});

/*--------------------------------------------------------------
# 3. Helper — normalize ACF image value
--------------------------------------------------------------*/
function cps_get_attachment_id_from_acf($acf_value){
    if(empty($acf_value)) return 0;
    if(is_numeric($acf_value)) return (int)$acf_value;
    if(is_array($acf_value)&&!empty($acf_value['ID'])) return (int)$acf_value['ID'];
    if(is_string($acf_value)){
        $id=attachment_url_to_postid($acf_value);
        return $id?:0;
    }
    return 0;
}

/*--------------------------------------------------------------
# 4. Shortcode
--------------------------------------------------------------*/
add_shortcode('collector_portraits_slider', function($atts){
    $a=shortcode_atts([
        'post_type'=>'collector',
        'field'=>'portrait',
        'image_size'=>'collector_portrait_thumb',
        'count'=>100,
        'orderby'=>'title',
        'order'=>'ASC',
        'link'=>'yes',
        'ratio'=>'4/5',
        'width'=>'200',
        'radius'=>'12',
        'autoplay'=>'yes',
        'speed'=>'8000',
        'pause'=>'yes',
        'reverse'=>'no',
    ],$atts,'collector_portraits_slider');

    $q=new WP_Query([
        'post_type'=>sanitize_key($a['post_type']),
        'posts_per_page'=>(int)$a['count'],
        'orderby'=>sanitize_key($a['orderby']),
        'order'=>$a['order']==='DESC'?'DESC':'ASC',
        'no_found_rows'=>true,
        'fields'=>'ids'
    ]);

    if(!$q->have_posts()) return '<div class="cps-empty">No collectors found.</div>';

    $uid='cps-'.wp_generate_password(6,false,false);
    $ratio=preg_replace('#[^0-9/\.]#','',$a['ratio']);
    $width=max(120,(int)$a['width']);
    $radius=max(0,(int)$a['radius']);
    $speed=max(1000,(int)$a['speed']);
    $style_vars=sprintf('--cps-ratio:%s;--cps-width:%dpx;--cps-radius:%dpx;',$ratio?:'4/5',$width,$radius);

    ob_start(); ?>
    <div class="cps-wrap"
         id="<?php echo esc_attr($uid); ?>"
         style="<?php echo esc_attr($style_vars); ?>"
         data-autoplay="<?php echo esc_attr($a['autoplay']); ?>"
         data-speed="<?php echo esc_attr($speed); ?>"
         data-pause="<?php echo esc_attr($a['pause']); ?>"
         data-reverse="<?php echo esc_attr($a['reverse']); ?>">

      <div class="swiper"><div class="swiper-wrapper">
        <?php
        foreach($q->posts as $post_id){
            $acf_value=function_exists('get_field')?get_field($a['field'],$post_id):null;
            $att_id=cps_get_attachment_id_from_acf($acf_value);
            if(!$att_id) continue;

            $file=basename(get_attached_file($att_id));
            if(preg_match('/^(Man_avatar|Woman_avatar)/i',$file)) continue; // skip avatars

            $title=get_the_title($post_id);
            $alt=get_post_meta($att_id,'_wp_attachment_image_alt',true) ?: $title;
            $img=wp_get_attachment_image($att_id,$a['image_size'],false,[
                'alt'=>esc_attr($alt),
                'loading'=>'lazy',
                'decoding'=>'async'
            ]);
            if(!$img) continue;

            $open=$a['link']==='yes'?'<a href="'.esc_url(get_permalink($post_id)).'" aria-label="'.esc_attr($title).'">':'';
            $close = !empty($a['link']) && strtolower(trim($a['link'])) === 'yes' ? '</a>' : '';
            echo '<div class="swiper-slide"><div class="cps-frame">'.$open.$img.$close.'</div></div>';
        }
        ?>
      </div></div>
    </div>
    <?php
    wp_reset_postdata();
    return ob_get_clean();
});
