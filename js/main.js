jQuery(document).ready(function($) {
  var visualSearch = VS.init({
    container  : $("#search_box_container"),
    query      : "",
    unquotable : [
      "text"
    ],
    callbacks  : {
      search : function(query, searchCollection) {
//		  enable the following line for search query debugging:
//        console.log(["query", searchCollection.facets(), query]);
		$("#usearch_response").addClass("loading");
		$(".usearch-result").animate({
			opacity: 0.5
			}, 500, function(){
			
		});
		var data = {
				action: "usearch_search",
				usearchquery: searchCollection.facets(),
				searchNonce: usearch_script.searchNonce
			};
			if ($("#usearch_response").length > 0 ){
				$.get(usearch_script.ajaxurl, data, function(response_from_get_results){
					$("#usearch_response").html(response_from_get_results);
					$("#usearch_response").removeClass("loading");
					$(".usearch-result").animate({
						opacity: 1
						}, 500, function(){

					});
					if(usearch_script.trackevents==true)
						_gaq.push(['_trackEvent', usearch_script.eventtitle, 'Submit', searchCollection.serialize(), parseInt(usearch_response.numresults)]);
				});
		  	} else {
				visualSearch.searchBox.addFacet("<span class='usearch-error-head'>Error</span>", "Ooops! I can't seem to find a results area! Did you read the documentation?");
				$('.usearch-error-head').parent().parent().addClass("usearch-error is_selected");
			}
			
//        var $query = $("#search_query");
//        $query.stop().animate({opacity : 1}, {duration: 300, queue: false});
//        $query.html("<span class=\'raquo\'>&raquo;</span> You searched for: <b>" + searchCollection.serialize() + "</b>");
//        clearTimeout(window.queryHideDelay2);
//        window.queryHideDelay2 = setTimeout(function() {
//          $query.animate({
//            opacity : 0
//          }, {
//            duration: 1000,
//            queue: false
//          });
//        }, 2000);
      },
      valueMatches : function(category, searchTerm, callback) {
        var data = {action: "usearch_getvalues"};
		 $.get(usearch_script.ajaxurl, data, function(response_from_get_values){
			switch (category) {
	          case "category":
	            callback(response_from_get_values.split(","));
	            break;
	        }
		  });
      },
      facetMatches : function(callback) {
        callback([
          "category"
        ], {
            preserveOrder: true
        });
      }
    }
  });
});