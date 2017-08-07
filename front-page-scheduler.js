jQuery( function () {

    // builds one rule editor section (a table that should be appended to the fieldset)
    // "vals" parameter must be an object with the properties of one rule
    // if it's an empty object, then an empty (new) section will be created
    _fps_strings.table_line = function ( i, vals ) {
        // initialize empty properties of vals object
        jQuery ( [ 'front_page_scheduler_page', 'front_page_scheduler_start', 'front_page_scheduler_stop', 'front_page_scheduler_weekday' ] ).each( function () {
            if ( ! ( this in vals ) ) vals[ this ] = '';
        } );
        if ( !jQuery.isArray( vals.front_page_scheduler_weekday ) ) vals.front_page_scheduler_weekday = [];
        // "ret" starts with the beginning of the markup
        var ret = '<table class="front_page_scheduler_rule_table" data-index="' + i + '"><tbody>';

        // string with <option> tags, populated with page names and their IDs
        var _str_pages = '<option value="0">' + _fps_strings[ 'none' ] + '</option>';
        jQuery( _fps_strings.pages ).each( function () {
            var _str_option_selected = ( vals.front_page_scheduler_page == this.id ) ? ' selected="selected"' : '';
            _str_pages += '<option value="' + this.id + '"' + _str_option_selected + '>' + this.title + '</option>';
        } );

        // string with <table> tag, containing checkboxes for the days of the week
        var _str_weekdays = '<table class="front_page_scheduler_weekday_table"><tbody><tr>';
        // day names on first row
        var _counter = 0;
        jQuery( _fps_strings['week-days-names'] ).each( function () {
            _str_weekdays += '<td><label for="front_page_scheduler_weekday_' + i + '_' + _counter + '">' + this + '</label></td>';
            _counter++;
        } );
        _str_weekdays += '</tr><tr>';
        // checkboxes on second row
        _counter = 0;
        _every_day_checked = ( jQuery.inArray( 0, vals.front_page_scheduler_weekday ) > -1 );
        jQuery( _fps_strings['week-days-names'] ).each( function () {
            var _this_day_checked = _every_day_checked || ( jQuery.inArray( _counter.toString(), vals.front_page_scheduler_weekday ) > -1 );
            var _str_day_checked = _this_day_checked ? ' checked="checked"' : '';
            _str_weekdays += '<td><input type="checkbox" id="front_page_scheduler_weekday_' + i + '_' + _counter + '" value="' + _counter + '"' + _str_day_checked + ' class="--chk-weekday" /></td>';
            _counter++;
        } );
        _str_weekdays += '</tr></tbody></table>';

        // "page" <select>
        ret += '<tr>\
                <th><label for="front_page_scheduler_page_' + i + '">' + _fps_strings[ 'alternate-front-page' ] + '</label></th>\
                <td><select id="front_page_scheduler_page_' + i + '">' + _str_pages + '</select></td>\
                </tr>';
        // "start at" <input>
        ret += '<tr>\
                <th><label for="front_page_scheduler_start_at_' + i + '">' + _fps_strings[ 'start-at' ] + '</label></th>\
                <td><input type="text" id="front_page_scheduler_start_at_' + i + '" maxlength="5" size="5" pattern="[0-9]{1,2}:[0-9]{1,2}" title="hh:mm" value="' + vals.front_page_scheduler_start + '" /> <span class="description">' + _fps_strings[ 'time-format' ] + '</span></td>\
                </tr>';
        // "week days" checkboxes
        ret += '<tr>\
                <th><label for="front_page_scheduler_weekday_' + i + '">' + _fps_strings[ 'week-days' ] + '</label></th>\
                <td>' + _str_weekdays + '</td>\
                </tr>';
        // "stop at" <input>
        ret += '<tr>\
                <th><label for="front_page_scheduler_stop_at_' + i + '">' + _fps_strings[ 'stop-at' ] + '</label></th>\
                <td><input type="text" id="front_page_scheduler_stop_at_' + i + '" maxlength="5" size="5" pattern="[0-9]{1,2}:[0-9]{1,2}" title="hh:mm" value="' + vals.front_page_scheduler_stop + '" /> <span class="description">' + _fps_strings[ 'time-format' ] + '</span></td>\
                </tr>';
        ret += '</tbody>';
        // "remove" button
        ret += '<tfoot>\
                <tr>\
                <td colspan="2"><button class="button --btn-remove-rule">' + _fps_strings[ 'remove-rule' ] + '</button></td>\
                </tr>\
                </tfoot></table>';
        // turning the string into an jQuery object
        ret = jQuery( ret );

        // on click "remove" button
        ret.find( '.--btn-remove-rule' ).on( 'click', function () {
            // are you sure?
            if ( confirm( _fps_strings[ 'remove-rule-confirm' ] ) ) {
                var _remove_btn = jQuery( this );
                // remove all the rule section
                _remove_btn.parents( 'table.front_page_scheduler_rule_table' ).first().fadeOut( 'fast', function () {
                    _remove_btn_parent = jQuery( this ).parent();
                    jQuery( this ).remove();
                    // and update the hidden value
                    _fps_strings.update_json( _remove_btn_parent );
                } );
            }
            // and do not submit the form
            return false;

        // on change "days of the week" checkboxes
        } ).end().find( '.--chk-weekday' ).on( 'change', function () {
            var _parent_table = jQuery( this ).parents( 'table.front_page_scheduler_rule_table' );
            // avoid auto-clicking loop
            if ( !_parent_table.hasClass( '--changing-checkboxes' ) ) {
                // block auto-clicking loop
                _parent_table.addClass( '--changing-checkboxes' );
                // all checkboxes
                var _winps = jQuery( '.--chk-weekday', _parent_table );
                // is this the "everyday" checkbox?
                if ( jQuery( this ).attr( 'value' ) == 0 ) {
                    // turn all checkboxes on or off
                    _winps.attr( 'checked', jQuery( this ).attr( 'checked' ) ? 'checked' : false );
                // is this a "regular" checkbox?
                } else {
                    // assume all checkboxes are checked
                    var _wall = true;
                    // each checkbox
                    _winps.each( function () {
                        // if it's not the "everyday" checkbox, and this is not checked, then not all checkboxes are checked
                        if ( !( jQuery( this ).attr( 'value' ) == 0 ) ) _wall = _wall && ( jQuery( this ).attr( 'checked' ) ? true : false );
                        // if not all checkboxes are checked, we already know what we need
                        if ( !_wall ) return;
                    } );
                    // turn the "everyday" checkbox on or off
                    _winps.filter( '[value="0"]' ).attr( 'checked', _wall ? 'checked' : false );
                }
                // unblock auto-clicking loop
                _parent_table.removeClass( '--changing-checkboxes' );
                // and update the hidden value
                _fps_strings.update_json( _parent_table );
            }
        
        // on change of every other input/select
        } ).end().find( 'select, input[type="text"]' ).on( 'change', function () {
            // update the hidden value
            _fps_strings.update_json( jQuery( this ) );
        } );
        return ret;
    }

    // updates the hidden value
    _fps_strings.update_json = function( obj ) {
        // if we do not receive the parameter, search by class name
        obj = obj ? jQuery( obj ) : jQuery( '.--json-container' );
        // if we receive an object without our expected class name, search parents by class name
        if ( !obj.is( '.--json-container' ) ) obj = obj.parents( '.--json-container' );
        // initialize empty array
        var _val = [];
        // each rule section
        jQuery( '.front_page_scheduler_rule_table', obj ).each( function () {
            var _tbl = jQuery( this );
            var _ind = _tbl.data( 'index' );
            // page selected on this rule section
            var _page = jQuery( '#front_page_scheduler_page_' + _ind, _tbl ).val();
            // any page selected?
            if ( _page ) {
                // get other fields
                var _start = jQuery( '#front_page_scheduler_start_at_' + _ind, _tbl ).val();
                var _stop = jQuery( '#front_page_scheduler_stop_at_' + _ind, _tbl ).val();
                // initialize empty array
                var _weekday = [];
                // each checkbox (days of the week)
                jQuery( '.--chk-weekday', _tbl ).each( function () {
                    // if it's checked
                    if ( jQuery( this ).is( ':checked' ) ) {
                        // get the value (number of the day)
                        var _inp_val = jQuery( this ).attr( 'value' );
                        // push into weekday array
                        _weekday.push( _inp_val );
                        // if it's zero (which means every day), break the loop
                        if ( _inp_val == 0 ) return false;
                    }
                } );
                // push fields' values into array
                _val.push( {
                    'front_page_scheduler_page' : _page,
                    'front_page_scheduler_start': _start,
                    'front_page_scheduler_stop': _stop,
                    'front_page_scheduler_weekday': _weekday
                } );
            }
        } );
        // JSON-serialize (stringify) array and update the hidden value
        jQuery( '#front_page_scheduler_json' ).val( JSON.stringify( _val ) );
    }

    // each hidden field
    jQuery( '#front_page_scheduler_json' ).each( function () {
        var _fieldset = jQuery( this ).parents( 'fieldset' );
        // "hidden"" rules
        var _rules = JSON.parse( jQuery( this ).val() );
        var _counter = 0;
        // each rule
        jQuery( _rules ).each( function () {
            // create and append rule section
            _counter++;
            _fieldset.append( _fps_strings.table_line( _counter, this ) );
        } );
        // "add rule" button
        var _add_button = jQuery( '<button class="button">' + _fps_strings[ 'add-rule' ] + '</button>' );
        // on click "add rule"
        _add_button.on( 'click', function () {
            // count existing rules
            var _existing_tables = _fieldset.children( '.front_page_scheduler_rule_table' );
            // add new rule after existing ones
            jQuery( this ).before( _fps_strings.table_line( _existing_tables.length + 1, {} ) );
            // update the hidden value
            _fps_strings.update_json( jQuery( this ) );
            // do not submit the form
            return false;
        } );
        // append "add rule" button
        _fieldset.append( _add_button );
    } );

} );