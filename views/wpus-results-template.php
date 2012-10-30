<?php
foreach($resultsarray as $item) {
	//	if(wpus_option('search_shortcodes')) { // if we're searching shortcodes, render them before formatting
	//		$excerpt = $this->wpus_strip_tags(apply_filters('the_content', $item->excerpt));
	//	} else {
	$excerpt = $this->wpus_strip_tags(apply_filters('strip_shortcodes', $item->excerpt));
	//	}
	$title = $item->post_title;
	if($keywords) { // if there are keywords, highlight them in the excerpt and title
		$excerpt = $this->highlightsearchterms($excerpt, $keywords);
		$title = $this->highlightsearchterms($item->post_title, $keywords);
	}
	$catstring = $this->render_categories($item); ?>

<div class="usearch-result">
	<div class="usearch-meta">
		<a href="<?php echo get_permalink($item->ID) ?>"><?php echo $title ?></a> <?php echo $catstring ?>
	</div>
	<div class="usearch-excerpt"><?php echo $excerpt ?></div>
</div>
<?php } ?>
