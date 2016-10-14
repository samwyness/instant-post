<?php
 /*
   Plugin Name: Instant Post for WordPress
   Description: A lightweight WordPress Plugin that allows you to instantly load your posts straight from the WP REST API.
   Author: Sam Wyness
   Version: 0.1
 */

// Load Instant Post Scripts //
add_action( 'wp_enqueue_scripts', 'ip_scripts' );
function ip_scripts() {
    // wp_register_style();
    wp_enqueue_style( '_ip-styles',  plugins_url( '/styles.css', __FILE__ ) );
}

//** The following functions create custom WP REST API fields **//

// Get post featured image url //
function ip_get_thumbnail_url( $post ){
    if ( has_post_thumbnail( $post[ 'id' ] ) ) {
        $imgArray = wp_get_attachment_image_src( get_post_thumbnail_id( $post[ 'id' ] ), 'full' );
        $imgURL = $imgArray[0];
        return $imgURL;
    } else {
        return false;
    }
}
// Add featured image url to WP REST API response //
add_action( 'rest_api_init', 'ip_insert_thumbnail_url' );
function ip_insert_thumbnail_url() {
    register_rest_field( 'post',
        'post_featured_img',
        array(
            'get_callback'    => 'ip_get_thumbnail_url',
            'update_callback' => null,
            'schema'          => null,
        )
    );
}

// Get post author details //
function ip_get_author_details( $object ) {
    $post_author = (int) $object['author'];
    $array_data = array();
    $array_data['first_name'] = get_user_meta($post_author, 'first_name', true);
    $array_data['last_name'] = get_user_meta($post_author, 'last_name', true);
    $array_data['nickname'] = get_user_meta($post_author, 'nickname', true);
    $array_data['user_avatar'] = get_avatar_url( get_the_author_meta( 'ID' ) );
    $array_data['username'] = get_the_author_meta('user_nicename');
    return array_filter( $array_data );
}
// Add post author details to WP REST API response //
add_action( 'rest_api_init', 'ip_author_details' );
function ip_author_details() {
    register_rest_field( 'post',
        'author_details',
        array(
            'get_callback'    => 'ip_get_author_details',
            'update_callback' => null,
            'schema'          => null,
        )
    );
}

//** Create shortcode This is the main function for controlling Instant Post **//
add_shortcode( 'add_instant_post', 'init_instant_post' );
function init_instant_post( $atts ) {
    $a = shortcode_atts( array(
        'ip_container'   => 'default',
        'ip_columns'     => 'default',
    ), $atts, 'add_instant_post' ); ?>

    <!-- JAVASCRIPT goes here -->
    <script type="text/javascript">

        //*** Wait for document to load before running functions ***//
        //*** ALL javascript must go inside this event listener  ***//
        document.addEventListener('DOMContentLoaded', function () {
            // Setup element variables //
            var body = document.body;
            var post_list = document.getElementById('post_list');
            var full_post_div = document.getElementById('full_post_div');
            var full_post_wrap = document.getElementById('full_post_wrap');
            var progress_bar = document.getElementById("progress_bar");
            var progress_overlay = document.getElementById("progress_overlay");
            var full_post_close = document.getElementById('full_post_close');
            var full_post_col_1 = document.getElementById('full_post_col_1');
            var full_post_col_2 = document.getElementById('full_post_col_2');

            //** Setup WP REST API requests **//
            var no_cache = new Date().getTime();

            // GET ALL POSTS //
            function getAllPosts( url, sendAllPosts ) {
                var xhttp = new XMLHttpRequest();
                xhttp.open( "GET", url, true );
                xhttp.onreadystatechange = function() {
                    if ( xhttp.readyState == 4 ) {
                        if ( xhttp.status == 200 ) {
                            var instant_post_data = JSON.parse( xhttp.responseText );
                            sendAllPosts( instant_post_data );
                        }
                    }
                };
                xhttp.send();
            }
            getAllPosts( "<?php echo esc_url( home_url( '/' ) ); ?>" + "wp-json/wp/v2/posts?filter[posts_per_page]=-1?" + no_cache, sendAllPosts );


            // Refresh all posts button //
            function refreshPosts( url, sendNewPosts ) {
                var xhttp = new XMLHttpRequest();
                xhttp.open( "GET", url, true );
                xhttp.onreadystatechange = function() {
                    if ( xhttp.readyState == 4 ) {
                        if ( xhttp.status == 200 ) {
                            var refresh_data = JSON.parse( xhttp.responseText );
                            sendNewPosts( refresh_data );
                        }
                    }
                };
                xhttp.send();
            }

            // Refresh Post Timer //
            setInterval( function() {
                var no_cache = new Date().getTime();
                refreshPosts( "<?php echo esc_url( home_url( '/' ) ); ?>" + "wp-json/wp/v2/posts?filter[posts_per_page]=-1?" + no_cache, sendNewPosts );
            }, 10000 );


            //** START BUILDING **//
            // Send all post data and build post list //
            function sendAllPosts( instant_post_data ) {
                for ( var i = 0; i < instant_post_data.length; i++ ) {
                    // Create post card wrap //
                    // This is where the post col layout is changed //
                    var post_card_wrap = document.createElement( 'div' );
                    post_card_wrap.className = "post_card_wrap " + "<?php echo $a['ip_columns'] ?>";
                    post_list.appendChild( post_card_wrap );

                    // Create post card //
                    var post_card = document.createElement( 'div' );
                    post_card.id = "post_card_" + instant_post_data[i].id;
                    post_card.className = "post_card";
                    post_card_wrap.appendChild( post_card );

                    // Create post card thumbnail wrap //
                    var post_card_thumb = document.createElement( 'div' );
                    post_card_thumb.id = "post_card_thumb";
                    post_card_thumb.className = "post_card_thumb";
                    post_card_thumb.style.backgroundImage = 'url(' + instant_post_data[i].post_featured_img + ')';
                    post_card.appendChild( post_card_thumb );

                    // Create post card title //
                    var post_card_title = document.createElement( 'p' );
                    post_card_title.id = "post_card_title";
                    post_card_title.className = "post_card_title";
                    post_card_title.innerHTML = instant_post_data[i].title.rendered;
                    post_card.appendChild( post_card_title );

                    // Create post author signature //
                    var post_card_signature = document.createElement( 'p' );
                    post_card_signature.className = "post_card_signature";
                    post_card_signature.style.fontSize = "80%";
                    var author_avatar_src = instant_post_data[i].author_details.user_avatar;
                    post_card_signature.innerHTML =
                    "By " + '<img class="author_avatar_src" src="' + author_avatar_src + '"/>' + // Author avatar
                    "<strong>" + instant_post_data[i].author_details.nickname + "</strong>" + // Author username
                    " - " + new Date( instant_post_data[i].date ).toLocaleString(); // Date posted
                    post_card.appendChild( post_card_signature );

                    // Create post card preview //
                    var post_card_excerpt = document.createElement( 'div' );
                    post_card_excerpt.id = "post_card_excerpt";
                    post_card_excerpt.className = "post_card_excerpt";
                    post_card_excerpt.innerHTML = instant_post_data[i].excerpt.rendered.substring(0, 135) + " ...";
                    post_card.appendChild( post_card_excerpt );

                    // Create read more button //
                    var read_more_btn = document.createElement( 'a' );
                    read_more_btn.href = "#" + instant_post_data[i].slug;
                    read_more_btn.dataset.id = instant_post_data[i].id;
                    read_more_btn.dataset.url = instant_post_data[i].slug;
                    read_more_btn.className = "read_more_btn";
                    read_more_btn.innerHTML = "Read More";
                    post_card.appendChild( read_more_btn );

                    // Read more btn Event listener //
                    read_more_btn.addEventListener("click", function() {

                        // Change URL to match clicked post //
                        // setTimeout( function() {
                        //     window.history.pushState(null, null, "")
                        // }, 100);

                        body.style.overflow = "hidden";
                        full_post_div.className += " slide_in_right";

                        // Add shadow to full_post_close on scroll //
                        full_post_wrap.onscroll = function() {
                            if (full_post_wrap.scrollTop < 15) {
                                full_post_close.style.boxShadow = "0px 0px 0px rgba(0,0,0,0)";
                            } else {
                                full_post_close.style.boxShadow = "0px 2px 5px rgba(0,0,0,0.14)";
                            }
                        }

                        // Load clicked post function //
                        var read_more_btn_id = this.dataset.id;
                        getClickedPost( instant_post_data, read_more_btn_id );

                        // Close full post wrap //
                        full_post_close.addEventListener("click", function() {
                            body.style.overflow = "auto";
                            full_post_div.className = "full_post_div";
                            setTimeout(function() {
                                full_post_wrap.className = "full_post_wrap container nopad";
                                full_post_col_1.innerHTML = "";
                                full_post_col_2.innerHTML = "";
                            }, 340);
                        });

                    }); // End Read More Btn
                }
            } // End Send All Posts fucntion


            // GET CLICKED POST //
            function getClickedPost( instant_post_data, read_more_btn_id ) {
                for (x = 0; x < instant_post_data.length; x++) {
                    // 'x' marks the spot //
                    if (instant_post_data[x].id == read_more_btn_id) {
                        var full_post_title = document.createElement( 'h2' );
                        full_post_title.className = "full_post_title";
                        full_post_title.innerHTML = instant_post_data[x].title.rendered;
                        full_post_col_2.appendChild( full_post_title );

                        var full_post_signature = document.createElement( 'p' );
                        full_post_signature.className = "full_post_signature";
                        full_post_signature.style.fontSize = "80%";
                        var author_avatar_src = instant_post_data[x].author_details.user_avatar;
                        full_post_signature.innerHTML =  "By " + '<img class="author_avatar_src" src="' + author_avatar_src + '"/>' + "<strong>" + instant_post_data[x].author_details.nickname + "</strong>" + " - " + new Date( instant_post_data[x].date ).toLocaleString();
                        full_post_col_2.appendChild( full_post_signature );

                        var post_featured_image = document.createElement( 'img' );
                        post_featured_image.className = "post_featured_image";
                        post_featured_image.src = instant_post_data[x].post_featured_img;
                        full_post_col_2.appendChild( post_featured_image );

                        var full_post_content = document.createElement( 'p' );
                        full_post_content.className = "full_post_content";
                        full_post_content.innerHTML = instant_post_data[x].content.rendered;
                        full_post_col_2.appendChild( full_post_content );

                    }
                }
            } // End get clicked post

            // Refresh Post List Alert and Update on click //
            function sendNewPosts( refresh_data ) {
                var current_post_count = document.getElementsByClassName('post_card').length;
                var new_post_count = refresh_data.length;
                if ( current_post_count == new_post_count ) {
                    return ;
                } else {
                    // check if new_posts_alert already exists //
                    if ( document.body.contains( document.getElementById('new_posts_alert') ) ) {
                        return ;
                    } else {
                        var new_posts_alert = document.createElement('span');
                        new_posts_alert.id = "new_posts_alert";
                        new_posts_alert.className = "new_posts_alert";
                        new_posts_alert.innerHTML = "New Posts, Click here to reload";
                        body.appendChild( new_posts_alert );
                        setTimeout(function() {
                            new_posts_alert.className += " new_posts_alert_enter";
                        }, 200);
                        // load new post on click //
                        new_posts_alert.addEventListener( "click", function() {
                            new_posts_alert.className = "new_posts_alert";
                            post_list.innerHTML = "";
                            sendAllPosts( refresh_data );
                            setTimeout(function() {
                                body.removeChild(document.getElementById('new_posts_alert'));
                            }, 3000);

                        });
                    }
                }
            }


        }); // END DOCUMENT LOAD TIMER //

    </script>

    <!-- HTML setup, simple and easy to use -->

    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">

    <!-- hero_section -->
    <div id="hero_section" class="hero_section <?php echo $a['ip_container'] ?>">
        <div id="post_list" class="post_list" data-ip-col=""></div>
    </div> <!-- /hero_section -->

    <!-- full_post_div -->
    <div id="full_post_div" class="full_post_div">
        <div id="progress_bar" class="progress_bar"></div>
        <div id="full_post_close" class="full_post_close"><i class="material-icons">keyboard_arrow_left</i>Back</div>
        <div id="progress_overlay" class="progress_overlay"></div>
        <div id="full_post_wrap" class="full_post_wrap ip_container nopad">
            <div id="full_post_col_1" class="full_post_col"></div>
            <div id="full_post_col_2" class="full_post_col"></div>
        </div>
    </div> <!-- /full_post_div -->

<?php
} // end init_instant_post
?>
