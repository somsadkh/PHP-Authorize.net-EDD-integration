; ( function ( document, $ ) {
	'use strict';
	let typingTimer;
	let searchInput = document.getElementById( 'edd-pro-search__input' );

	if ( searchInput ) {
		searchInput.addEventListener( 'keyup', function ( event ) {
			startSearch();
		} );

		searchInput.addEventListener( 'search', function ( event ) {
			startSearch();
		} );
	}

	function startSearch () {
		clearTimeout( typingTimer );
		typingTimer = setTimeout( downloadsSearch, 500 );
	}

	function downloadsSearch () {
		// Locate the card elements
		let items = document.querySelectorAll( '.edd-pro-search__product' );

		// Locate the search input
		let search_query = searchInput.value.toLowerCase();

		let count = 0;
		// Loop through the items
		for ( var i = 0; i < items.length; i++ ) {

			if ( !search_query ) {
				items[ i ].classList.remove( 'edd-pro-search__hidden' );
				count = items.length;
				continue;
			}
			// card text
			let innertext = items[ i ].innerText.toLowerCase().includes( search_query );
			// card data-filter
			let filter = items[ i ].dataset.filter && items[ i ].dataset.filter.toLowerCase().includes( search_query );
			if ( innertext || filter ) {
				count++;
				items[ i ].classList.remove( 'edd-pro-search__hidden' );
			} else {
				items[ i ].classList.add( 'edd-pro-search__hidden' );
			}
		}
		$( '.edd-pro-search__results' ).remove();
		$( '#edd-pro-search__input' ).after( '<div role="status" class="edd-pro-search__results screen-reader-text">' + count + ' ' + EDDBlocksSearch.results + '</div>' )
	}
} )( document, jQuery );
