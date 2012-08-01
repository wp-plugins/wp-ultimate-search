jQuery(document).ready(function($) {
  var visualSearch = VS.init({
    container  : $("#search_box_container"),
    query      : "",
    unquotable : [
      "text"
    ],
    callbacks  : {
      search : function(query, searchCollection) {
//        console.log(["query", searchCollection.facets(), query]);

		var data = {
				action: "usearch_search",
				usearchquery: searchCollection.facets(),
				searchNonce: usearch_script.searchNonce
			};

		  $.get(usearch_script.ajaxurl, data, function(response_from_get_results){
				$("#usearch_response").html(response_from_get_results);
		  });
        var $query = $("#search_query");
        $query.stop().animate({opacity : 1}, {duration: 300, queue: false});
        $query.html("<span class=\'raquo\'>&raquo;</span> You searched for: <b>" + searchCollection.serialize() + "</b>");
        clearTimeout(window.queryHideDelay2);
        window.queryHideDelay2 = setTimeout(function() {
          $query.animate({
            opacity : 0
          }, {
            duration: 1000,
            queue: false
          });
        }, 2000);
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