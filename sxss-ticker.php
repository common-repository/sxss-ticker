<?php
/*
Plugin Name: sxss Ticker
Plugin URI: http://sxss.info
Description: Saves and displays short messages
Author: sxss
Version: 0.1.1 Beta
*/

/*	
	Settingspage
	
		jQuery (slide open)
		
		Generator (form that generates shortcode)
		
		Mobile Page (inkl Formular)
*/

add_filter('widget_text', 'do_shortcode');

function pre($content)
{
	echo "<pre>";
	
	var_dump($content);
	
	echo "</pre>";
}

// I18n
load_plugin_textdomain('sxss_ticker', false, basename( dirname( __FILE__ ) ) . '/languages' );

// delete link
function sxss_ticker_delete_link()
{
	if ( true == current_user_can('delete_posts') )
	{
		
		$return = '<a href="' . get_delete_post_link( $post->ID ) .'">Delete post</a>';
		
		return $return;
	}
}

// filter signature
function sxss_ticker_set($message, $ticker_name)
{
	// filter message
	$message = wp_filter_post_kses($message);
	
	$user = wp_get_current_user();
	
	$userid = $current_user->ID;
	
	// get ticker category id by name
	$term = get_term_by( 'name', $ticker_name, 'ticker_name' );
	
	// if it doesn't exist
	if( $term == false ) 
	{
		// create category
		wp_insert_term( $ticker_name, 'ticker_name');
		
		// get category id by name
		$term = get_term_by( 'name', $ticker_name, 'ticker_name' );
	}
	
	// get the term_id
	$term_id = $term->term_id;

	// insert message
	$ticker = wp_insert_post( array(
		'post_title' => $message,
		'post_type' 	=> 'ticker',
		// 'post_name'	 => $ticker['slug'],
		// 'comment_status' => 'closed',
		// 'ping_status' => 'closed',
		// 'post_content' => $ticker['content'],
		'post_status' => 'publish',
		'post_author' => $userid,
		'menu_order' => 0,
		'tax_input' => array( 'ticker_name' => $term_id )
	));

	return $message;
}

function sxss_ticker_form($ticker_name = false)
{
	// save new postings
	if ($_POST['sxss_ticker_save'] == 'yes' && $_POST["sxss_ticker_new"] != "")
	{
		$new = sxss_ticker_set($_POST["sxss_ticker_new"], $_POST["sxss_ticker_name"]);
	}
	
	if( $ticker_name == false ) 
	{
		$name_field .= '<p><select name="sxss_ticker_name">';
		
			$the_terms = get_terms( 'ticker_name', array(
				'orderby'    => 'count',
				'hide_empty' => 0,
				'order' => 'desc'
				) );
		
			foreach($the_terms as $term)
			{
				$name_field .= '<option value="' . $term->slug . '">' . $term->name . '</option>';
			}
			
		$name_field .= '</select></p>';
	}
	else 
	{
		$name_field = '<input type="hidden" name="sxss_ticker_name" value="' . $ticker_name . '" />';
	}
	
	// form
	$return .= '
		
		<form id="sxss_ticker_form" style="margin: 0; padding: 0;" method="post" action="">

			<input type="hidden" name="sxss_ticker_save" value="yes" />

			<p><textarea style="width: 100%; height: 60px;" name="sxss_ticker_new"></textarea></p>
			
			' . $name_field . '

			<p><input type="submit" class="button-primary" value="' . __('Save message', 'sxss_ticker') . '" /></p>

		</form>
	
	';
	
	return $return;
}




function sxss_clickable_links($text) { 

  $text = eregi_replace('(((f|ht){1}tp://)[-a-zA-Z0-9@:%_\+.~#?&//=]+)', 
    '<a href="\\1">\\1</a>', $text); 
  $text = eregi_replace('([[:space:]()[{}])(www.[-a-zA-Z0-9@:%_\+.~#?&//=]+)', 
    '\\1<a href="http://\\2">\\2</a>', $text); 
  $text = eregi_replace('([_\.0-9a-z-]+@([0-9a-z][0-9a-z-]+\.)+[a-z]{2,4})', 
    '<a href="mailto:\\1">\\1</a>', $text); 
   
return $text; 

} 


function sxss_ticker_get_data($ticker_name, $number, $dateformat)
{
	$i = 0;

	$args = array( 'post_type' => 'ticker', 'ticker_name' => $ticker_name, 'posts_per_page' => $number );
	
	$loop = new WP_Query( $args );
	
	while ( $loop->have_posts() ) : $loop->the_post();
		
		$return[$i]["time"] = get_the_time($dateformat);
		$return[$i]["edit_link"] = get_edit_post_link();
		$return[$i]["delete_link"] = get_delete_post_link( $post->ID );
		$return[$i]["content"] = sxss_clickable_links( get_the_title() );

		$i++;

	endwhile;
	
	// if no ticker messages, register ticker
	if($i == 0)
	{
		// try to get term
		$term = get_term_by( 'name', $ticker_name, 'ticker_name' );
		
		// if it doesn't exist
		if( $term == false ) 
		{
			// create category
			wp_insert_term( $ticker_name, 'ticker_name');
		}

	}
	
	return $return;
}

// Shortcode

function sxss_ticker($atts, $content)
{
	// get shortcode parameter
	extract( shortcode_atts( array(
		'title' => '',
		'style' => 'list',
		'number' => 10,
		'time' => 'false',
		'name' => '',
		'dateformat' => 'Y-m-d',
		'speed' => 3,
		'form' => 'true'
	), $atts ) );
	
	// count
	$i = 0;
	
	if( $style == "table")
	{
		// start table
		$return .= '<table class="shortcodeticker">' . "\n";
	
		// display title if exist
		if( $title != "" ) $return .= '<tr><td colspan="2"><strong>' . $title . '</strong></td></tr>' . "\n";

		// authors can post messages in the ticker
		if( true == current_user_can('publish_posts') && $form == "true" )
		{
			$return .= '<tr><td colspan="2">' . sxss_ticker_form($name) . '</td></tr>';
		}
	
		// get ticker messages
		$posts = sxss_ticker_get_data($name, $number, $dateformat);
	
		// if there are messages
		if( $posts != NULL )
		{
			foreach($posts as $post)
			{
				// add alternative class to every second row
				if( $i / 2 ) $class_alt = ' sxss_ticker_alt';
		
				$return .= '<tr valign="top" class="sxss_ticker_count_' . $i . '' . $class_alt . '">' . "\n";
		
				// if time should be displayed
				if( $time == "true" ) 
				{
					$timestamp = '<br /><span style="font-size: 80%; color: #C0C0C0;">' . $post["time"]. '</span>';
				}
			
				// edit & delete link
				if( true == current_user_can('edit_posts') && true == current_user_can('delete_posts') ) $adminlinks = '<div style="float: right; padding: 0 0 3px 5px;"><a href="'.$post["edit_link"].'"><img src="' . plugins_url('/images/comment_edit.png', __FILE__) . '"></a> <a onclick="return confirm(\'Are you sure you want to delete?\');" href="' . $post["delete_link"] . '"><img src="' . plugins_url('/images/comment_delete.png', __FILE__) . '"></a></div>';

				$return .= '<td>' . $adminlinks . $post["content"] . $timestamp . '</td>' . "\n";

				$return .= "</tr>\n";
		
				$i++;
				
			} // end foreach
			
		} // end if($posts)
	
		$return .= "</table>\n";
	}
	elseif( $style == "marquee")
	{
		// get ticker messages
		$posts = sxss_ticker_get_data($name, $number, $dateformat);
	
		// if there are messages
		if( $posts != NULL )
		{			
			foreach($posts as $post)
			{
		

			
				// edit & delete link
				// if( true == current_user_can('edit_posts') && true == current_user_can('delete_posts') ) $adminlinks = ' <a class="sxss_ticker_edit_link" href="'.$post["edit_link"].'">edit</a> <a class="sxss_ticker_delete_link" onclick="return confirm(\'Are you sure you want to delete?\');" href="' . $post["delete_link"] . '">delete</a>';

				// if time should be displayed
				if( $time == "true" ) 
				{
					$timestamp = ' <span class="sxss_ticker_meta">(' . $post["time"]. ')</span>';
				}
				
				$marqueeposts .= $post["content"] . $timestamp . $adminlinks . " +++ ";
				
			} // end foreach
			
			#$marqueeposts = str_replace('/', '\/', $marqueeposts);
			#$marqueeposts = str_replace("\'", "'", $marqueeposts);
			
			$return .= '
			
				<script>
				
					var tWidth="100%";					// width (in pixels)
					var tHeight="25px";					// height (in pixels)
					var tcolour="#ffffcc";				// background colour:
					var moStop=true;					// pause on mouseover (true or false)
					var tSpeed=' . $speed . ';			// scroll speed (1 = slow, 5 = fast)

					// enter your ticker content here (use \/ and \' in place of / and \' respectively)
					var content=\'' . $marqueeposts . '\';

					// Simple Marquee / Ticker Script
					// copyright 3rd January 2006, Stephen Chapman
					// permission to use this Javascript on your web page is granted
					// provided that all of the below code in this script (including this
					// comment) is used without any alteration
					var cps=tSpeed; var aw, mq; var fsz = parseInt(tHeight) - 4; function startticker(){if (document.getElementById) {var tick = \'<div style="position:relative; height:\'+tHeight+\';overflow:hidden; "\'; if (moStop) tick += \' onmouseover="cps=0" onmouseout="cps=tSpeed"\'; tick +=\'><div id="mq" style="position:absolute;left:0px;top:0px;white-space:nowrap;"><\/div><\/div>\'; document.getElementById(\'sxss_ticker_marquee\').innerHTML = tick; mq = document.getElementById("mq"); mq.style.left=(parseInt(tWidth)+10)+"px"; mq.innerHTML=\'<span id="tx">\'+content+\'<\/span>\'; aw = document.getElementById("tx").offsetWidth; lefttime=setInterval("scrollticker()",50);}} function scrollticker(){mq.style.left = (parseInt(mq.style.left)>(-10 - aw)) ?parseInt(mq.style.left)-cps+"px" : parseInt(tWidth)+10+"px";} window.onload=startticker;
									  
				  </script><div id="sxss_ticker_marquee"></div>';
			
		} // end if($posts)
		
		if( true == current_user_can('publish_posts') && $form == "true" )
		{
			$return .= sxss_ticker_form($name);
		}
	
	}
	else // list
	{
		if( true == current_user_can('publish_posts') && $form == "true" )
		{
			$return .= sxss_ticker_form($name);
		}
		
		// get ticker messages
		$posts = sxss_ticker_get_data($name, $number, $dateformat);
	
		// if there are messages
		if( $posts != NULL )
		{
			$return .= '<ul>';
			
			foreach($posts as $post)
			{
				// add alternative class to every second row
				if( $i / 2 ) $class_alt = ' sxss_ticker_alt';
		
				$return .= '<li class="sxss_ticker_count_' . $i . '' . $class_alt . '">' . "\n";
			
				// edit & delete link
				if( true == current_user_can('edit_posts') && true == current_user_can('delete_posts') ) $adminlinks = ' <a class="sxss_ticker_edit_link" href="'.$post["edit_link"].'">edit</a> <a class="sxss_ticker_delete_link" onclick="return confirm(\'Are you sure you want to delete?\');" href="' . $post["delete_link"] . '">delete</a>';

				// if time should be displayed
				if( $time == "true" ) 
				{
					$timestamp = ' <span style="color: #C0C0C0; font-size: 90%;" class="sxss_ticker_meta">' . $post["time"]. '</span>';
				}
				
				$return .= $post["content"] . $timestamp . $adminlinks . "\n";

				$return .= "</li>\n";
		
				$i++;
				
			} // end foreach
			
		} // end if($posts)
	
		$return .= "</ul>\n";
	
	}
	
	return $return;
}

add_shortcode( 'ticker', 'sxss_ticker' );

















// settingspage
function sxss_ticker_settings() 
{
	// save settings
	if ($_POST['action'] == 'update')
	{
		if( $_POST['sxss_ticker_expert'] == "1") update_option('sxss_ticker_expert', 1);
		else update_option('sxss_ticker_expert', 0);
		
		$message = '<div id="message" class="updated fade"><p><strong>' . __('Settings updated', 'sxss_ticker') . '</strong></p></div>'; 
	} 

	// get settings from db

	if( get_option('sxss_ticker_expert') == 1 ) 
	{
		$sxss_expert = "checked ";
	}


	echo '

	<div class="wrap" style="max-width: 650px;">

		'.$message.'

		<div id="icon-options-general" class="icon32"><br /></div>

		<h2>' . __('sxss Ticker', 'sxss_ticker') . '</h2>

		<form method="post" action="">

		<input type="hidden" name="action" value="update" />
		
		<p><input type="checkbox" name="sxss_ticker_expert" value="1" ' . $sxss_expert . '/> ' . __('Display Expert Informations', 'sxss_ticker') . ' 

		 <input type="submit" class="button-primary" value="' . __('Save settings', 'sxss_ticker') . '" /></p>

		</form>
		
		<br />

		
		<h3>Thanks for using sxss Ticker!</h4>
		
		<p>You can embedd the sxss Ticker easyly with s shortcode <code>[ticker]</code>. The shortcode has many parameters to customize the look and function of the ticker.</p>
		
		<h3>Example usage</h3>
		
		<ol>
		
			<li><code>[ticker style="table" name="news"]</code></li>
					
			<li><code>[ticker style="table" name="Movie Ticker" number="10" time="true" dateformat="Y-m-d H:i:s" title="Latest Movie News"]</code></li>
		
			<li><code>[ticker style="list" name="Basketball Ticker" number="5" dateformat="d.m.Y"]</code></li>
		
		</ol>
		
		<p>Or put this in your theme file:<br />
		<code>&lt;?php echo do_shortcode(&#039;[ticker style=&quot;table&quot; name=&quot;Movie Ticker&quot; number=&quot;10&quot; time=&quot;true&quot; dateformat=&quot;Y-m-d H:i:s&quot; title=&quot;Latest Movie News&quot;]&#039;); ?&gt;</code></p>
		
		<h3>Options</h3>
		<p>
			<strong>name</strong> <br />
			
			<blockquote>
				
				A unique name for the specific ticker that will be used to identify the messages (<strong>name</strong> will not be displayed)
			
				<ol>
					<li>string</li>
				</ol>
			
			</blockquote>
		</p>
		
		<p>
			<strong>style</strong>
			
			<blockquote>Use one of the three options
			
				<ol>
					<li>list <span class="description">(default)</span></li>
					<li>table</li>
					<li>marquee</li>
				</ol>
				
			</blockquote>
			
		</p>
		
		<p>
			<strong>title</strong> <span class="description">(optional)</span>

			<blockquote>
		
				If you use a table, the title will be displayed above the messages
			
				<ol>
					<li>string</li>
				</ol>
				
			</blockquote>
		
		</p>
		
		<p>
			<strong>number</strong> <span class="description">(optional, default: 10)</span>
			
			<blockquote>

				Number of messages to show
				
				<ol>
					<li>integer</li>
				</ol>
				
			</blockquote>
			
		</p>
		
		<p>
			<strong>form</strong> <span class="description">(optional, defaul: true)</span>

			<blockquote>
			
				Display a form to post new entries. If you have two tickers with a form on the same page, there will be problems.
				
				<ol>
					<li>true</li>
					<li>false</li>
				</ol>
			
			</blockquote>
			
		</p>
		
		<p>
			<strong>time</strong> <span class="description">(optional, defaul: false)</span>

			<blockquote>
			
				Display timestamp
				
				<ol>
					<li>true</li>
					<li>false</li>
				</ol>
			
			</blockquote>
			
		</p>
		
		<p>
			<strong>dateformat</strong> <span class="description">(optional, default: Y-m-d)</span>
			
			<blockquote>
			
				How the timestamp should be displayed (<a target="_blank" class="description" href="http://codex.wordpress.org/Formatting_Date_and_Time">documentation</a>)
			
				<ol>
					<li>string</li>
				</ol>
				
			</blockquote>
				
		</p>
		
		<p>
			<strong>speed</strong> <span class="description">(optional, default: 3)</span>
			
			<blockquote>
			
				Speed of the marquee ticker
			
				<ol>
					<li>integer</li>
				</ol>
				
			</blockquote>
				
		</p>

	</div>';
} 
 


// register settings page
function sxss_ticker_admin_menu()
{  
	add_options_page(__('sxss Ticker', 'sxss_ticker'), __('sxss Ticker', 'sxss_ticker'), 9, 'sxss_ticker', 'sxss_ticker_settings');  
}  

add_action("admin_menu", "sxss_ticker_admin_menu"); 










function sxss_ticker_init_dashboard_widgets()
{
	if( true == current_user_can('publish_posts') )
	{
		global $wp_meta_boxes;

		wp_add_dashboard_widget('sxss_ticker_widget', __('sxss Ticker', 'sxss_ticker'), 'sxss_ticker_dashboard_widget');
	}
}

add_action('wp_dashboard_setup', 'sxss_ticker_init_dashboard_widgets');


function sxss_ticker_dashboard_widget() 
{
	echo '<div class="wrap">';

	echo sxss_ticker_form($name);
		
	echo '</div>';
}








// register custom post type

add_action( 'init', 'sxss_ticker_register_cpt' );

function sxss_ticker_register_cpt() {

	if( get_option('sxss_ticker_expert') == 1 ) $showoption = true;
	else $showoption = false;
	
    $labels = array( 
        'name' => _x( 'Messages', 'message' ),
        'singular_name' => _x( 'message', 'message' ),
        'add_new' => _x( 'Add new', 'message' ),
        'add_new_item' => _x( 'Add new message', 'message' ),
        'edit_item' => _x( 'Edit message', 'message' ),
        'new_item' => _x( 'New message', 'message' ),
        'view_item' => _x( 'View message', 'message' ),
        'search_items' => _x( 'Search messages', 'message' ),
        'not_found' => _x( 'No messages found', 'message' ),
        'not_found_in_trash' => _x( 'No messages found in trash', 'message' ),
        'parent_item_colon' => _x( 'Parent message:', 'message' ),
        'menu_name' => _x( 'Ticker Messages', 'ticker' ),
    );

    $args = array( 
        'labels' => $labels,
        'hierarchical' => false,
        
        'supports' => array( 'title', 'author' ),
		'taxonomies' => array( 'categories' ),
        
        'public' => true,
        'show_ui' => $showoption,
        'show_in_menu' => true,
        'menu_position' => 20,
        
        'show_in_nav_menus' => false,
        'publicly_queryable' => false,
        'exclude_from_search' => true,
        'has_archive' => false,
        'query_var' => true,
        'can_export' => true,
        'rewrite' => true,
        'capability_type' => 'post'
    );

    register_post_type( 'ticker', $args );
}

// register cpt taxonomy
add_action( 'init', 'register_taxonomy_ticker_categories' );

function register_taxonomy_ticker_categories() {

    $labels = array( 
        'name' => _x( 'Tickers', 'sxss_ticker' ),
        'singular_name' => _x( 'Ticker', 'sxss_ticker' ),
        'search_items' => _x( 'Search Tickers', 'sxss_ticker' ),
        'popular_items' => _x( 'Popular Tickers', 'sxss_ticker' ),
        'all_items' => _x( 'All Tickers', 'sxss_ticker' ),
        'parent_item' => _x( 'Parent Ticker', 'sxss_ticker' ),
        'parent_item_colon' => _x( 'Parent Ticker:', 'sxss_ticker' ),
        'edit_item' => _x( 'Edit Ticker', 'sxss_ticker' ),
        'update_item' => _x( 'Update Ticker', 'sxss_ticker' ),
        'add_new_item' => _x( 'Add New Ticker', 'sxss_ticker' ),
        'new_item_name' => _x( 'New Ticker', 'sxss_ticker' ),
        'separate_items_with_commas' => _x( 'Separate tickers with commas', 'sxss_ticker' ),
        'add_or_remove_items' => _x( 'Add or remove tickers', 'sxss_ticker' ),
        'choose_from_most_used' => _x( 'Choose from the most used tickers', 'sxss_ticker' ),
        'menu_name' => _x( 'Tickers', 'sxss_ticker' ),
    );

    $args = array( 
        'labels' => $labels,
        'public' => false,
        'show_in_nav_menus' => false,
        'show_ui' => true,
        'show_tagcloud' => false,
        'hierarchical' => true,

        'rewrite' => true,
        'query_var' => true
    );

    register_taxonomy( 'ticker_name', array('ticker'), $args );
}

/*
function sxss_ticker_admin_bar($wp_toolbar)
{
	$wp_toolbar->add_menu( array(
		'id' => 'sxss_ticker_form',
		'title' => 'Ticker',
		'href' => '#',
		'meta' => array('onclick' => 'alert("alles ist cool!")')
	) );
}

add_action('admin_bar_menu', 'sxss_ticker_admin_bar', 999);
*/
?>