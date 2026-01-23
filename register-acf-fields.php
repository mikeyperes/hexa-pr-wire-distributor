<?php namespace hpr_distributor; 

function register_acf_rss(){
	if ( ! function_exists( 'acf_add_local_field_group' ) ) {
		return;
	}
 
	acf_add_local_field_group( array(
	'key' => 'group_66e9ebd79f8e0',
	'title' => 'RSS Structures',
	'fields' => array(
		array(
			'key' => 'field_66e9ebd8336f9',
			'label' => 'RSS - Post Type',
			'name' => 'rss_post_type',
			'aria-label' => '',
			'type' => 'repeater',
			'instructions' => '',
			'required' => 0,
			'conditional_logic' => 0,
			'wrapper' => array(
				'width' => '',
				'class' => '',
				'id' => '',
			),
			'layout' => 'table',
			'pagination' => 0,
			'min' => 0,
			'max' => 0,
			'collapsed' => '',
			'button_label' => 'Add Row',
			'rows_per_page' => 20,
			'sub_fields' => array(
				array(
					'key' => 'field_66e9ec35336fc',
					'label' => 'Post Slug',
					'name' => 'slug',
					'aria-label' => '',
					'type' => 'text',
					'instructions' => '',
					'required' => 0,
					'conditional_logic' => 0,
					'wrapper' => array(
						'width' => '',
						'class' => '',
						'id' => '',
					),
					'default_value' => '',
					'maxlength' => '',
					'placeholder' => '',
					'prepend' => '',
					'append' => '',
					'parent_repeater' => 'field_66e9ebd8336f9',
				),
				array(
					'key' => 'field_66e9ec3b336fd',
					'label' => 'RSS ID',
					'name' => 'rss_id',
					'aria-label' => '',
					'type' => 'text',
					'instructions' => '',
					'required' => 0,
					'conditional_logic' => 0,
					'wrapper' => array(
						'width' => '',
						'class' => '',
						'id' => '',
					),
					'default_value' => '',
					'maxlength' => '',
					'placeholder' => '',
					'prepend' => '',
					'append' => '',
					'parent_repeater' => 'field_66e9ebd8336f9',
				),
			),
		),
		array(
			'key' => 'field_66e9ec8523bda',
			'label' => 'RSS - Category Type',
			'name' => 'rss_post_category',
			'aria-label' => '',
			'type' => 'repeater',
			'instructions' => '',
			'required' => 0,
			'conditional_logic' => 0,
			'wrapper' => array(
				'width' => '',
				'class' => '',
				'id' => '',
			),
			'layout' => 'table',
			'pagination' => 0,
			'min' => 0,
			'max' => 0,
			'collapsed' => '',
			'button_label' => 'Add Row',
			'rows_per_page' => 20,
			'sub_fields' => array(
				array(
					'key' => 'field_66e9ec8523bdb',
					'label' => 'Category Slug',
					'name' => 'slug',
					'aria-label' => '',
					'type' => 'text',
					'instructions' => '',
					'required' => 0,
					'conditional_logic' => 0,
					'wrapper' => array(
						'width' => '',
						'class' => '',
						'id' => '',
					),
					'default_value' => '',
					'maxlength' => '',
					'placeholder' => '',
					'prepend' => '',
					'append' => '',
					'parent_repeater' => 'field_66e9ec8523bda',
				),
				array(
					'key' => 'field_66e9ec8523bdc',
					'label' => 'RSS ID',
					'name' => 'rss_id',
					'aria-label' => '',
					'type' => 'text',
					'instructions' => '',
					'required' => 0,
					'conditional_logic' => 0,
					'wrapper' => array(
						'width' => '',
						'class' => '',
						'id' => '',
					),
					'default_value' => '',
					'maxlength' => '',
					'placeholder' => '',
					'prepend' => '',
					'append' => '',
					'parent_repeater' => 'field_66e9ec8523bda',
				),
			),
		),
	),
	'location' => array(
		array(
			array(
				'param' => 'options_page',
				'operator' => '==',
				'value' => 'rss-structures',
			),
		),
	),
	'menu_order' => 0,
	'position' => 'normal',
	'style' => 'default',
	'label_placement' => 'top',
	'instruction_placement' => 'label',
	'hide_on_screen' => '',
	'active' => true,
	'description' => '',
	'show_in_rest' => 0,
) );



	acf_add_options_page( array(
	'page_title' => 'RSS Structures',
	'menu_slug' => 'rss-structures',
	'redirect' => false,
) );


}
?>