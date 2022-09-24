(function($){
	$.fn.filter_visible = function(depth) {
		depth = depth || 3;
		var is_visible = function() {
			var p = $(this), i;
			for(i=0; i<depth-1; ++i) {
				if (!p.is(':visible')) return false;
				p = p.parent();
			}
			return true;
		};
		return this.filter(is_visible);
	};
	$.table_hotkeys = function(table, keys, opts) {
		opts = $.extend($.table_hotkeys.defaults, opts);
		var selected_class, destructive_class, set_current_row, adjacent_row_callback, get_adjacent_row, adjacent_row, prev_row, next_row, check, get_first_row, get_last_row, make_key_callback, first_row;
		
		selected_class = opts.class_prefix + opts.selected_suffix;
		destructive_class = opts.class_prefix + opts.destructive_suffix;
		set_current_row = function (tr) {
			if ($.table_hotkeys.current_row) $.table_hotkeys.current_row.removeClass(selected_class);
			tr.addClass(selected_class);
			tr[0].scrollIntoView(false);
			$.table_hotkeys.current_row = tr;
		};
		adjacent_row_callback = function(which) {
			if (!adjacent_row(which) && $.isFunction(opts[which+'_page_link_cb'])) {
				opts[which+'_page_link_cb']();
			}
		};
		get_adjacent_row = function(which) {
			var first_row, method;
			
			if (!$.table_hotkeys.current_row) {
				first_row = get_first_row();
				$.table_hotkeys.current_row = first_row;
				return first_row[0];
			}
			method = 'prev' == which? $.fn.prevAll : $.fn.nextAll;
			return method.call($.table_hotkeys.current_row, opts.cycle_expr).filter_visible()[0];
		};
		adjacent_row = function(which) {
			var adj = get_adjacent_row(which);
			if (!adj) return false;
			set_current_row($(adj));
			return true;
		};
		prev_row = function() { return adjacent_row('prev'); };
		next_row = function() { return adjacent_row('next'); };
		check = function() {
			$(opts.checkbox_expr, $.table_hotkeys.current_row).each(function() {
				this.checked = !this.checked;
			});
		};
		get_first_row = function() {
			return $(opts.cycle_expr, table).filter_visible().eq(opts.start_row_index);
		};
		get_last_row = function() {
			var rows = $(opts.cycle_expr, table).filter_visible();
			r