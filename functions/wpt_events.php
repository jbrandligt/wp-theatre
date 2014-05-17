<?php
class WPT_Events extends WPT_Listing {

	/**
	 * An array of all categories with upcoming events.
	 * @since 0.5
	 */
	function categories($filters=array()) {
		// get all events according to remaining filters
		$filters['category'] = false;
		$events = $this->get($filters);		
		$categories = array();
		foreach ($events as $event) {
			$post_categories = wp_get_post_categories( $event->production()->ID );
			foreach($post_categories as $c){
				$cat = get_category( $c );
				$categories[$cat->slug] = $cat->name;
			}
		}
		asort($categories);
		
		return $categories;
		
	}
	
	function defaults() {
		return array(
			'limit' => false,
			'upcoming' => false,
			'past' => false,
			'month' => false,
			'category' => false,
			'season' => false,
			'production' => false,
			'status' => array('publish')
		);
	}
	
	/**
	 * A list of upcoming events in HTML.
	 * 
	 * Example:
	 *
	 * $args = array('paginateby'=>'month');
	 * echo $wp_theatre->events->html($args); // a list of all upcoming events, paginated by month
	 *
	 * @since 0.5
	 *
	 * @param array $args {
	 *     An array of arguments. Optional.
	 *
	 *     @type bool $paged Paginate the list by month. Default <false>.
	 *     @type bool $grouped Group the list by month. Default <false>.
	 *     @type int $limit Limit the list to $limit events. Use <false> for an unlimited list. Default <false>.
	 * }
 	 * @return string HTML.
	 */
	public function html($args=array()) {
		global $wp_theatre;
		global $wp_query;

		$defaults = array(
			'paginateby' => array(),
			'groupby'=>false,
			'production' => false,
			'season' => false,
			'limit' => false,
			'category' => false,
			'template' => NULL
		);
		$args = wp_parse_args( $args, $defaults );

		$classes = array();
		$classes[] = "wpt_events";

		// Thumbnail
		if (!empty($args['template']) && strpos($args['template'],'{{thumbnail}}')===false) { 
			$classes[] = 'wpt_events_without_thumbnail';
		}

		$current_url = add_query_arg(
			array(
				'wpt_month' => $wp_query->query_vars['wpt_month'],
				'wpt_category' => $wp_query->query_vars['wpt_category'],
				'wpt_season', $wp_query->query_vars['wpt_season']
			)
		);

		$html = '';

		$filters = array(
			'upcoming' => true,
			'production' => $args['production'],
			'limit' => $args['limit'],
			'category' => $args['category'],
			'season' => $args['season']
		);

		if ($category = get_category_by_slug($wp_query->query_vars['wpt_category'])) {
  			$filters['category'] = $category->term_id;				
		}

		if (!empty($wp_query->query_vars['wpt_month'])) {
			$filters['month'] = $wp_query->query_vars['wpt_month'];
		}			


		/*
		 * Months navigation
		 */
		if (
			in_array('month',$args['paginateby']) ||
			!empty($wp_query->query_vars['wpt_month'])
		) {
			$html_filter_months = '';

			foreach($this->months($filters) as $month) {
				$url = remove_query_arg('wpt_month', $current_url);
				if (sanitize_title($month) != $wp_query->query_vars['wpt_month']) {
					if (!in_array('month',$args['paginateby'])) {
						continue;
					}
					$url = add_query_arg('wpt_month', sanitize_title($month) , $url);
				}
				
				$url = apply_filters('wpt_events_paginate_url', $url);
				$html_filter_months.= '<span><a href="'.$url.'">'.date_i18n('M Y',strtotime($month)).'</a></span>';
			}

			$html.= '<nav class="wpt_events_months">'.$html_filter_months.'</nav>';

		}

		/*
		 * Categories navigation
		 */
		if (
			in_array('category',$args['paginateby']) ||
			!empty($wp_query->query_vars['wpt_category'])
		) {
			$html_filter_categories = '';		

			foreach($this->categories($filters) as $slug=>$name) {
				$url = remove_query_arg('wpt_category', $current_url);
				if ($slug != $wp_query->query_vars['wpt_category']) {
					if (!in_array('category',$args['paginateby'])) {
						continue;
					}
					$url = add_query_arg('wpt_category', $slug , $url);										
				}

				$url = apply_filters('wpt_events_paginate_url', $url);
				$html_filter_categories.= '<span><a href="'.$url.'">'.$name.'</a></span>';
			}
			
			$html.= '<nav class="wpt_events_categories">'.$html_filter_categories.'</nav>';
		}

		/*
		 * Seasons navigation
		 */
		if (in_array('season',$args['paginateby'])) {
			$seasons = $wp_theatre->productions->seasons();

			if (!empty($_GET[__('season','wp_theatre')])) {
				$filters['season'] = $_GET[__('season','wp_theatre')];
			} else {
				$slugs = array_keys($seasons);
				$filters['season'] = $slugs[0];				
			}

			$html.= '<nav>';
			foreach($seasons as $slug=>$season) {

				$url = remove_query_arg(__('season','wp_theatre'));
				$url = add_query_arg( __('season','wp_theatre'), $slug , $url);
				$html.= '<span>';

				$title = $season->title();
				if ($slug == $filters['season']) {
					$html.= $title;
				} else {
					$html.= '<a href="'.$url.'">'.$title.'</a>';					
				}
				$html.= '</span>';
			}
			$html.= '</nav>';
		}


		$event_args = array();
		if (isset($args['template'])) { 
			$event_args['template'] = $args['template']; 
		}

		
		switch ($args['groupby']) {
			case 'month':
				if (!in_array('month', $args['paginateby'])) {
					$months = $this->months($filters);
					foreach($months as $month) {
						$filters['month'] = $month;
						$events = $this->get($filters);
						if (!empty($events)) {
							$html.= '<h3>'.date_i18n('F',strtotime($month)).'</h3>';
							foreach ($events as $event) {
								$html.=$event->html($event_args);							
							}
						}
					}
					break;					
				}
			case 'category':
				if (!in_array('category', $args['paginateby'])) {
					$categories = $this->categories($filters);
					foreach($categories as $slug=>$name) {
						if ($category = get_category_by_slug($slug)) {
				  			$filters['category'] = $category->term_id;				
						}
						$events = $this->get($filters);
						if (!empty($events)) {
							$html.= '<h3>'.$name.'</h3>';
							foreach ($events as $event) {
								$html.=$event->html($event_args);							
							}							
						}
					}
					break;					
				}
			default:
				$events = $this->get($filters);
				foreach ($events as $event) {
					$html.=$event->html($event_args);							
				}
		}

		// Wrapper
		$html = '<div class="'.implode(' ',$classes).'">'.$html.'</div>'; 
		
		return $html;
	}
	
	/**
	 * Setup the current selection of events.
	 * 
	 * @since 0.5
	 *
 	 * @return array Events.
	 */
	function load($filters=array()) {
		global $wpdb;
		global $wp_theatre;
		
		$filters = wp_parse_args( $filters, $this->defaults() );
		$args = array(
			'post_type' => WPT_Event::post_type_name,
			'post_status' => $filters['status'],
			'meta_query' => array(),
			'order' => 'asc'
		);
		
		if ($filters['upcoming']) {
			$args['meta_query'][] = array (
				'key' => $wp_theatre->order->meta_key,
				'value' => time(),
				'compare' => '>='
			);
		}

		if ($filters['production']) {
			$args['meta_query'][] = array (
				'key' => WPT_Production::post_type_name,
				'value' => $filters['production'],
				'compare' => '='
			);
		}
		
		if ($filters['month']) {
			$args['meta_query'][] = array (
				'key' => 'event_date',
				'value' => $filters['month'],
				'compare' => 'LIKE'
			);
		}

		if ($filters['season']) {
			$args['meta_query'][] = array (
				'key' => WPT_Season::post_type_name,
				'value' => $filters['season'],
				'compare' => '='
			);
		}
		
		if ($filters['category']) {
			$args['cat'] = $filters['category'];
		}
		
		if ($filters['limit']) {
			$args['posts_per_page'] = $filters['limit'];
		} else {
			$args['posts_per_page'] = -1;
			
		}

		$posts = get_posts($args);
		$events = array();
		for ($i=0;$i<count($posts);$i++) {
			$key = $posts[$i]->ID;
			$event = new WPT_Event($posts[$i]->ID);
			$events[] = $event;
		}

		return $events;
	}

	/**
	 * An array of all months with upcoming events.
	 * @since 0.5
	 */
	function months($filters=array()) {
		// get all event according to remaining filters
		$filters['month'] = false;
		$events = $this->load($filters);		
		$months = array();
		foreach ($events as $event) {
			$months[] = date('Y-m',$event->datetime());
		}
		$months = array_unique($months);
		sort($months);

		return $months;
	}
	
	
	public function meta($args=array()) {
		$defaults = array(
			'paged' => false,
			'grouped' => false,
			'production' => false,
		);
		$args = wp_parse_args( $args, $defaults );

		$html = '';

		$filters = array(
			'upcoming' => true,
			'production' => $args['production']
		);

		$events = $this->get($filters);
		
		$uniqid = uniqid();
		
		for($i=0;$i<count($events);$i++) {
			$html.= $events[$i]->meta();
		}

		return $html;
	}
		
}
?>