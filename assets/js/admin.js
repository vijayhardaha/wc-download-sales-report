( function( $ ) {
  const APP = {
    init: () => {
      APP.dismissIcon = `<span role="button" tabindex="0" class="dismiss"><svg height="24" width="24" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><g><path d="M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12 19 6.41z"></path></g></svg></span>`;
      APP.noticeIcon = `<span class="notice-icon"><svg height="24" width="24" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><g><path d="M12 2C6.477 2 2 6.477 2 12s4.477 10 10 10 10-4.477 10-10S17.523 2 12 2zm1 15h-2v-6h2v6zm0-8h-2V7h2v2z"></path></g></svg></span>`;
      APP.checkIcon = `<span class="notice-icon"><svg height="24" width="24" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><g><path d="M9 19.414l-6.707-6.707 1.414-1.414L9 16.586 20.293 5.293l1.414 1.414"></path></g></svg></span>`;

      APP.noticeTimeout = false;

      // Register sales report events.
      APP.registerSalesReportEvents();
    },
    getElement: function( selector ) {
      return $( document ).find( selector );
    },
    showNotice: ( type = "info", text = "", autohide = "yes" ) => {
      let html;

      clearTimeout( APP.noticeTimeout );

      APP.getElement( ".notices-container" ).remove();

      if ( text == "" ) {
        switch ( type ) {
          case "success":
            text = "Updated settings.";
            break;
          case "error":
            text = "Update settings failed.";
            break;
          case "warning":
            text = "Update settings failed.";
            break;
          case "info":
          default:
            text = "Updating settings...";
            break;
        }
      }

      if ( type === "success" ) {
        html = `<div class="notices-container"><div class="notice-box is-${type}">${APP.checkIcon}<span class="notice-content"><span class="notice-text">${text}</span></span>${APP.dismissIcon}</div></div>`;
      } else {
        html = `<div class="notices-container"><div class="notice-box is-${type}">${APP.noticeIcon}<span class="notice-content"><span class="notice-text">${text}</span></span>${APP.dismissIcon}</div></div>`;
      }

      APP.getElement( ".wc-download-sales-report-container" ).append( html );

      if ( autohide === "yes" ) {
        APP.noticeTimeout = setTimeout( function() {
          APP.getElement( ".notices-container" ).fadeOut().remove();
        }, 5000 );
      }
    },
    removeAllNotices: () => {
      APP.getElement( ".notices-container" ).remove();
    },
    registerNoticeEvents: () => {
      $( document )
        .on( "click", ".notice-box .dismiss", ( e ) => {
          e.preventDefault();
          $( e.currentTarget ).parents( ".notice-box" ).hide().remove();
          if ( APP.getElement( ".notices-container" ).length && APP.getElement( ".notices-container" ).html().trim() == "" ) {
            APP.getElement( ".notices-container" ).remove();
          }
        } );
    },
    registerDatePickersEvents: () => {
      if ( APP.getElement( ".setting-field .datepicker" ).length ) {
        APP.getElement( ".setting-field .datepicker" ).datepicker( {
          dateFormat: "yy-mm-dd",
          changeMonth: true,
          changeYear: true,
          yearRange: "-100:+1",
          maxDate: "0"
        } );
      }
    },
    registerSalesReportEvents: () => {
      // Register notice events.
      APP.registerNoticeEvents();

      //Register date picker events
      APP.registerDatePickersEvents();


      if ( APP.getElement( ".download-sales-report-form :input#report-time" ).val() === "custom" ) {
        APP.getElement( ".download-sales-report-form #setting-custom-date-range" ).slideDown();
      } else {
        APP.getElement( ".download-sales-report-form #setting-custom-date-range" ).slideUp();
      }

      APP.switchReportPeriod();
      APP.switchIncludeProducts();

      $( document )
        .on( "click", ".download-sales-report-form .view-report", function( e ) {
          e.preventDefault();

          const form = $( e.currentTarget ).closest( "form" );
          form.find( "input[name='download']" ).val( 0 );

          APP.removeAllNotices();

          $.ajax( {
            type: 'POST',
            url: ajaxurl,
            data: form.serialize(),
            dataType: 'json',
            beforeSend: function() {
              form.find( '[type="button"]' ).attr( 'disabled', true );

              APP.showNotice( "info", "Generating Reports...", "no" );

              $( 'html,body' ).animate( {
                scrollTop: 0
              }, 800 );
            },
            success: function( response ) {
              form.find( '[type="button"]' ).attr( 'disabled', false );

              APP.removeAllNotices();

              if ( response.success ) {
                $( ".wc-download-sales-report-container" ).append( response.data.html );
                setTimeout( () => {
                  $( ".wc-download-sales-report-container .side-panel-wrapper" ).addClass( "open" );
                }, 100 );
              } else {
                APP.showNotice( "error", "Sorry, Something went wrong." );
              }
            },
            error: function() {
              form.find( '[type="button"]' ).attr( 'disabled', false );
              APP.showNotice( "error", "Sorry, Something went wrong." );
            }
          } );
        } )

        .on( "click", ".download-sales-report-form .download-report", function( e ) {
          e.preventDefault();

          const form = $( e.currentTarget ).closest( "form" );
          form.find( "input[name=download]" ).val( 1 );

          APP.removeAllNotices();

          $.ajax( {
            type: "POST",
            url: ajaxurl,
            data: form.serialize(),
            dataType: "json",
            beforeSend: function() {
              form.find( "[type=button]" ).attr( "disabled", true );

              APP.showNotice( "info", "Generating CSV File...", "no" );

              $( "html,body" ).animate( {
                scrollTop: 0
              }, 800 );
            },
            success: function( response ) {
              form.find( "[type=button]" ).attr( "disabled", false );

              APP.removeAllNotices();

              if ( response.success ) {
                document.querySelector( ".download-sales-report-form .download-url" ).setAttribute( "href", response.data.redirect );
                document.querySelector( ".download-sales-report-form .download-url" ).click();
              } else {
                APP.showNotice( "error", "Sorry, Something went wrong." );
              }
            },
            error: function() {
              form.find( "[type=button]" ).attr( "disabled", false );
              APP.showNotice( "error", "Sorry, Something went wrong." );
            }
          } );
        } )

        .on( "change", ".download-sales-report-form :input#setting-report-time", ( e ) => {
          APP.switchReportPeriod( $( e.currentTarget ).val() );
        } )

        .on( "click", ".download-sales-report-form :input[name='products']", function() {
          APP.switchIncludeProducts();
        } )

        .on( 'click', '.wc-download-sales-report-container .side-panel-wrapper', function( event ) {
          if ( $( event.target ).closest( '.side-panel-content' ).length === 0 ) {
            APP.getElement( ".wc-download-sales-report-container .side-panel-wrapper" ).removeClass( "open" );
            setTimeout( function() {
              APP.getElement( ".wc-download-sales-report-container .side-panel-wrapper" ).remove();
            }, 100 );
          }
        } );
    },
    switchReportPeriod: ( value = '' ) => {
      if ( value == '' ) {
        value = APP.getElement( ".download-sales-report-form :input#setting-report-time" ).val();
      }
      if ( value === "custom" ) {
        APP.getElement( ".download-sales-report-form #setting-row-custom-date-range" ).slideDown();
      } else {
        APP.getElement( ".download-sales-report-form #setting-row-custom-date-range" ).slideUp();
      }
    },
    switchIncludeProducts: ( value = '' ) => {
      if ( value == '' ) {
        value = $( ".download-sales-report-form input[name='products']:checked" ).val();
      }
      switch ( value ) {
        case "categories":
          APP.getElement( ".download-sales-report-form #product-cats-lists" ).slideDown();
          APP.getElement( ".download-sales-report-form #product-ids-field" ).slideUp();
          break;
        case "ids":
          APP.getElement( ".download-sales-report-form #product-cats-lists" ).slideUp();
          APP.getElement( ".download-sales-report-form #product-ids-field" ).slideDown();
          break;
        default:
          APP.getElement( ".download-sales-report-form #product-cats-lists" ).slideUp();
          APP.getElement( ".download-sales-report-form #product-ids-field" ).slideUp();
          break;
      }
    }
  };

  APP.init();
} )( jQuery );