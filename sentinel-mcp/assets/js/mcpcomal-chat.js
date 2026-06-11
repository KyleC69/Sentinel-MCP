/**
 * Chat AI — Fullscreen chat application.
 *
 * Vanilla JS. Layout:
 * - Sidebar with conversation list + user menu popup
 * - Welcome screen with greeting + centered input
 * - Chat view with messages + bottom input
 * - Tool call accordions
 *
 * @package SENTINEL
 * @since   1.1.0
 */

( function () {
	'use strict';

	var config = window.mcpcomalChat || {};
	var i18n   = config.i18n || {};

	// ─── State ───────────────────────────────────────────────────

	var state = {
		conversations: [],
		activeConversationId: null,
		messages: [],
		isSending: false,
		searchOpen: false,
		sidebarOpen: false,
	};

	// ─── DOM Cache ───────────────────────────────────────────────

	var el = {};

	// ─── API Layer ───────────────────────────────────────────────

	var api = {
		call: function ( endpoint, method, body ) {
			method = method || 'GET';
			var opts = {
				method: method,
				headers: {
					'X-WP-Nonce': config.nonce,
					'Content-Type': 'application/json',
				},
			};
			if ( body ) {
				opts.body = JSON.stringify( body );
			}
			return fetch( config.restUrl + endpoint, opts ).then( function ( res ) {
				return res.json();
			} );
		},

		listConversations: function () {
			return api.call( 'conversations' );
		},

		createConversation: function ( provider, model ) {
			return api.call( 'conversations', 'POST', { provider: provider || '', model: model || '' } );
		},

		getConversation: function ( id ) {
			return api.call( 'conversations/' + id );
		},

		deleteConversation: function ( id ) {
			return api.call( 'conversations/' + id, 'DELETE' );
		},

		renameConversation: function ( id, title ) {
			return api.call( 'conversations/' + id, 'PATCH', { title: title } );
		},

		sendMessage: function ( conversationId, message ) {
			return api.call( 'send', 'POST', { conversation_id: conversationId, message: message } );
		},

		searchConversations: function ( query ) {
			return api.call( 'search?q=' + encodeURIComponent( query ) );
		},

		switchProvider: function ( conversationId, provider, model ) {
			return api.call( 'switch-provider', 'POST', { conversation_id: conversationId, provider: provider, model: model } );
		},
	};

	// ─── Markdown ────────────────────────────────────────────────

	var md = {
		render: function ( text ) {
			if ( ! text ) {
				return '';
			}
			try {
				var html = marked.parse( text, { breaks: true, gfm: true } );
				return DOMPurify.sanitize( html, { ADD_ATTR: [ 'target' ] } );
			} catch ( e ) {
				return esc( text );
			}
		},

		highlightAll: function ( container ) {
			if ( typeof hljs !== 'undefined' ) {
				container.querySelectorAll( 'pre code' ).forEach( function ( block ) {
					hljs.highlightElement( block );
				} );
			}
		},
	};

	// ─── Helpers ─────────────────────────────────────────────────

	function esc( str ) {
		if ( ! str ) {
			return '';
		}
		var d = document.createElement( 'div' );
		d.textContent = str;
		return d.innerHTML;
	}

	function getGreeting() {
		var h = new Date().getHours();
		var name = config.userName || '';
		var timeStr;
		if ( h < 12 ) {
			timeStr = 'Good morning';
		} else if ( h < 18 ) {
			timeStr = 'Good afternoon';
		} else {
			timeStr = 'Good evening';
		}
		return name ? timeStr + ', ' + name : timeStr;
	}

	function autoResize( textarea ) {
		textarea.style.height = 'auto';
		textarea.style.height = Math.min( textarea.scrollHeight, 200 ) + 'px';
	}

	function getModelList() {
		var list = [];
		var providers = config.providers || {};
		Object.keys( providers ).forEach( function ( pid ) {
			var p = providers[ pid ];
			if ( ! p.has_key ) {
				return;
			}
			var models = p.models || {};
			Object.keys( models ).forEach( function ( mid ) {
				list.push( {
					provider: pid,
					model: mid,
					providerLabel: p.label,
					modelLabel: models[ mid ],
					isDefault: pid === config.defaultProvider && mid === p.default,
				} );
			} );
		} );
		return list;
	}

	function getDefaultModel() {
		var list = getModelList();
		for ( var i = 0; i < list.length; i++ ) {
			if ( list[ i ].isDefault ) {
				return list[ i ];
			}
		}
		return list[0] || { provider: '', model: '', providerLabel: 'AI', modelLabel: 'Select model' };
	}

	function buildModelPicker( id ) {
		var def = getDefaultModel();
		return '<div class="sentinel-model-picker" id="' + id + '">' +
			'<button class="sentinel-model-picker-btn" type="button">' +
				'<span class="sentinel-model-picker-label">' + esc( def.modelLabel ) + '</span>' +
				'<span class="sentinel-model-picker-chevron dashicons dashicons-arrow-down-alt2"></span>' +
			'</button>' +
			'<div class="sentinel-model-picker-dropdown">' +
				buildModelDropdownItems() +
			'</div>' +
		'</div>';
	}

	function buildModelDropdownItems() {
		var providers = config.providers || {};
		var html = '';
		var first = true;

		Object.keys( providers ).forEach( function ( pid ) {
			var p = providers[ pid ];
			if ( ! first ) {
				html += '<div class="sentinel-model-picker-sep"></div>';
			}
			first = false;

			html += '<div class="sentinel-model-picker-group">' + esc( p.label ) + '</div>';

			if ( ! p.has_key ) {
				html += '<div class="sentinel-model-picker-no-key">-- No AI provider configured</div>';
				return;
			}

			var models = p.models || {};
			Object.keys( models ).forEach( function ( mid ) {
				var isDefault = pid === config.defaultProvider && mid === p.default;
				var cls = isDefault ? ' active' : '';
				html += '<div class="sentinel-model-picker-item' + cls + '" data-provider="' + pid + '" data-model="' + mid + '">' +
					'<span class="sentinel-check">&#10003;</span>' +
					'<span>' + esc( models[ mid ] ) + '</span>' +
				'</div>';
			} );
		} );
		return html;
	}

	// ─── UI ──────────────────────────────────────────────────────

	var ui = {
		init: function () {
			var app = document.getElementById( 'sentinel-chat-app' );
			if ( ! app ) {
				return;
			}
			app.removeAttribute( 'data-loading' );
			app.innerHTML = '';

			if ( ! config.hasApiKey ) {
				ui.renderNoApiKey( app );
				return;
			}

			app.innerHTML = ui.buildLayout();
			ui.cacheElements();
			handlers.bindAll();
			ui.loadConversations();
		},

		cacheElements: function () {
			el.sidebar         = document.querySelector( '.sentinel-chat-sidebar' );
			el.chatList        = document.querySelector( '.sentinel-chat-list' );
			el.searchBox       = document.querySelector( '.sentinel-chat-search-box' );
			el.searchInput     = document.querySelector( '.sentinel-chat-search-input' );
			el.welcome         = document.querySelector( '.sentinel-chat-welcome' );
			el.welcomeTextarea = document.getElementById( 'sentinel-welcome-textarea' );
			el.welcomePicker   = document.getElementById( 'sentinel-welcome-picker' );
			el.welcomeSendBtn  = document.getElementById( 'sentinel-welcome-send' );
			el.chatView        = document.querySelector( '.sentinel-chat-view' );
			el.messages        = document.querySelector( '.sentinel-chat-messages' );
			el.chatTextarea    = document.getElementById( 'sentinel-chat-textarea' );
			el.chatSendBtn     = document.getElementById( 'sentinel-chat-send' );
			el.chatPicker      = document.getElementById( 'sentinel-chat-picker' );
			el.usage           = document.querySelector( '.sentinel-chat-usage' );
			el.footer          = document.querySelector( '.sentinel-chat-sidebar-footer' );

			var def = getDefaultModel();
			state.selectedProvider = def.provider;
			state.selectedModel    = def.model;
		},

		buildLayout: function () {
			var userName = config.userName || 'Admin';
			var avatarHtml = config.userAvatar
				? '<img src="' + esc( config.userAvatar ) + '" alt="" class="sentinel-chat-user-avatar">'
				: '<div class="sentinel-chat-user-avatar-letter">' + esc( userName.charAt( 0 ).toUpperCase() ) + '</div>';

			return '' +
				/* ── Sidebar ── */
				'<div class="sentinel-chat-sidebar">' +
					'<div class="sentinel-chat-sidebar-header">' +
						'<button class="sentinel-chat-new-btn" type="button">' +
							'<span class="dashicons dashicons-plus-alt2"></span> ' + esc( i18n.newChat ) +
						'</button>' +
					'</div>' +
					'<div class="sentinel-chat-sidebar-nav">' +
						'<button class="sentinel-chat-nav-item" id="sentinel-search-toggle" type="button">' +
							'<span class="dashicons dashicons-search"></span> Search' +
						'</button>' +
						'<button class="sentinel-chat-nav-item active" id="sentinel-chats-toggle" type="button">' +
							'<span class="dashicons dashicons-format-chat"></span> Chats' +
						'</button>' +
					'</div>' +
					'<div class="sentinel-chat-search-box hidden" id="sentinel-search-box">' +
						'<input type="text" class="sentinel-chat-search-input" placeholder="' + esc( i18n.searchPlaceholder ) + '" autocomplete="off">' +
					'</div>' +
					'<div class="sentinel-chat-sidebar-label">Recent</div>' +
					'<div class="sentinel-chat-list"></div>' +
					'<div class="sentinel-chat-sidebar-footer">' +
						'<div class="sentinel-chat-popup-menu">' +
							'<a href="' + esc( config.adminUrl ) + '" class="sentinel-chat-popup-item">' +
								'<span class="dashicons dashicons-dashboard"></span> Dashboard' +
							'</a>' +
							'<a href="' + esc( config.adminUrl ) + 'options-connectors.php" class="sentinel-chat-popup-item">' +
								'<span class="dashicons dashicons-admin-generic"></span> AI Settings' +
							'</a>' +
							'<div class="sentinel-chat-popup-sep"></div>' +
							'<a href="' + esc( config.adminUrl ) + '" class="sentinel-chat-popup-item">' +
								'<span class="dashicons dashicons-arrow-left-alt"></span> ' + esc( i18n.backToAdmin ) +
							'</a>' +
						'</div>' +
						avatarHtml +
						'<span class="sentinel-chat-user-name">' + esc( userName ) + '</span>' +
						'<span class="sentinel-chat-footer-chevron dashicons dashicons-arrow-up-alt2"></span>' +
					'</div>' +
				'</div>' +
				'<div class="sentinel-chat-sidebar-overlay"></div>' +

				/* ── Main ── */
				'<div class="sentinel-chat-main">' +

					/* Welcome screen */
					'<div class="sentinel-chat-welcome">' +
						'<div class="sentinel-chat-welcome-center">' +
							'<div class="sentinel-chat-welcome-card">' +
								'<span class="sentinel-chat-welcome-icon dashicons dashicons-format-chat"></span>' +
								'<h1 class="sentinel-chat-welcome-title">' + esc( i18n.welcomeTitle ) + '</h1>' +
								'<p class="sentinel-chat-welcome-subtitle">' + esc( i18n.welcomeSubtitle ) + '</p>' +
								'<div class="sentinel-chat-suggestions">' +
									'<button class="sentinel-chat-suggestion" type="button">' + esc( i18n.welcomeSuggestion1 ) + '</button>' +
									'<button class="sentinel-chat-suggestion" type="button">' + esc( i18n.welcomeSuggestion2 ) + '</button>' +
									'<button class="sentinel-chat-suggestion" type="button">' + esc( i18n.welcomeSuggestion3 ) + '</button>' +
									'<button class="sentinel-chat-suggestion" type="button">' + esc( i18n.welcomeSuggestion4 ) + '</button>' +
								'</div>' +
							'</div>' +
						'</div>' +
						'<div class="sentinel-chat-welcome-bottom">' +
							'<div class="sentinel-chat-input-wrap">' +
								'<textarea id="sentinel-welcome-textarea" rows="1" placeholder="' + esc( i18n.inputPlaceholder ) + '"></textarea>' +
								'<button class="sentinel-chat-send-btn" id="sentinel-welcome-send" type="button">' +
									'<span class="dashicons dashicons-arrow-up-alt"></span>' +
								'</button>' +
							'</div>' +
							'<div class="sentinel-chat-welcome-bottom-meta">' +
								buildModelPicker( 'sentinel-welcome-picker' ) +
								'<span class="sentinel-chat-welcome-hint">Enter = send &middot; Shift+Enter = new line &middot; Esc = back</span>' +
							'</div>' +
						'</div>' +
					'</div>' +

					/* Chat view */
					'<div class="sentinel-chat-view hidden">' +
						'<div class="sentinel-chat-topbar">' +
							'<div class="sentinel-chat-topbar-left">' +
								'<button class="sentinel-chat-toggle-sidebar" type="button">' +
									'<span class="dashicons dashicons-menu"></span>' +
								'</button>' +
								buildModelPicker( 'sentinel-chat-picker' ) +
							'</div>' +
							'<span class="sentinel-chat-usage"></span>' +
						'</div>' +
						'<div class="sentinel-chat-messages"></div>' +
						'<div class="sentinel-chat-input">' +
							'<div class="sentinel-chat-input-wrap">' +
								'<textarea id="sentinel-chat-textarea" rows="1" placeholder="' + esc( i18n.inputPlaceholder ) + '"></textarea>' +
								'<button class="sentinel-chat-send-btn" id="sentinel-chat-send" type="button">' +
									'<span class="dashicons dashicons-arrow-up-alt"></span>' +
								'</button>' +
							'</div>' +
						'</div>' +
					'</div>' +

				'</div>';
		},

		renderNoApiKey: function ( container ) {
			container.innerHTML = '' +
				'<div class="sentinel-chat-no-key">' +
					'<span class="dashicons dashicons-warning"></span>' +
					'<p>' + esc( i18n.noApiKey ) + '</p>' +
					'<a href="' + esc( config.settingsUrl ) + '">' + esc( i18n.goToSettings ) + '</a>' +
				'</div>';
		},

		loadConversations: function () {
			api.listConversations().then( function ( data ) {
				if ( data.success ) {
					state.conversations = data.conversations || [];
					ui.renderChatList();
				}
			} );
		},

		renderChatList: function () {
			if ( ! el.chatList ) {
				return;
			}
			var convs = state.conversations;
			if ( ! convs.length ) {
				el.chatList.innerHTML = '<div class="sentinel-chat-empty-list">' + esc( i18n.noConversations ) + '</div>';
				return;
			}
			var html = '';
			convs.forEach( function ( c ) {
				var cls = parseInt( c.id ) === state.activeConversationId ? ' active' : '';
				html += '<div class="sentinel-chat-item' + cls + '" data-id="' + c.id + '">' +
					'<span class="sentinel-chat-item-title">' + esc( c.title ) + '</span>' +
					'<span class="sentinel-chat-item-delete" data-id="' + c.id + '">&times;</span>' +
				'</div>';
			} );
			el.chatList.innerHTML = html;
		},

		showWelcome: function () {
			state.activeConversationId = null;
			state.messages = [];
			if ( el.welcome ) {
				el.welcome.classList.remove( 'hidden' );
			}
			if ( el.chatView ) {
				el.chatView.classList.add( 'hidden' );
			}
			if ( el.messages ) {
				el.messages.innerHTML = '';
			}
			ui.renderChatList();
			ui.closeSidebar();
		},

		showChat: function ( convId ) {
			state.activeConversationId = parseInt( convId );
			if ( el.welcome ) {
				el.welcome.classList.add( 'hidden' );
			}
			if ( el.chatView ) {
				el.chatView.classList.remove( 'hidden' );
			}
			ui.renderChatList();
			ui.closeSidebar();
		},

		loadConversation: function ( id ) {
			ui.showChat( id );
			el.messages.innerHTML = '<div class="sentinel-typing"><div class="sentinel-typing-dot"></div><div class="sentinel-typing-dot"></div><div class="sentinel-typing-dot"></div></div>';

			api.getConversation( id ).then( function ( data ) {
				if ( data.success ) {
					state.messages = data.messages || [];
					state.selectedProvider = data.conversation.provider;
					state.selectedModel    = data.conversation.model;

					var list = getModelList();
					var found = list.filter( function ( m ) {
						return m.provider === data.conversation.provider && m.model === data.conversation.model;
					} );
					if ( found.length ) {
						document.querySelectorAll( '.sentinel-model-picker-label' ).forEach( function ( lbl ) {
							lbl.textContent = found[0].modelLabel;
						} );
						document.querySelectorAll( '.sentinel-model-picker-item' ).forEach( function ( it ) {
							it.classList.remove( 'active' );
						} );
						document.querySelectorAll( '.sentinel-model-picker-item[data-provider="' + data.conversation.provider + '"][data-model="' + data.conversation.model + '"]' ).forEach( function ( it ) {
							it.classList.add( 'active' );
						} );
					}

					ui.renderMessages();
				}
			} );
		},

		renderMessages: function () {
			if ( ! el.messages ) {
				return;
			}
			var html = '';
			state.messages.forEach( function ( msg ) {
				if ( msg.role === 'user' ) {
					html += '<div class="sentinel-msg-user">' + esc( msg.content ) + '</div>';
				} else if ( msg.role === 'assistant' ) {
					html += ui.renderAssistantMessage( msg );
				}
			} );
			el.messages.innerHTML = html;
			md.highlightAll( el.messages );
			ui.bindToolToggles();
			ui.scrollToBottom();
		},

		renderAssistantMessage: function ( msg ) {
			var html = '<div class="sentinel-msg-assistant">' + md.render( msg.content ) + '</div>';

			if ( msg.tool_calls && msg.tool_calls.length ) {
				msg.tool_calls.forEach( function ( tc ) {
					html += ui.renderToolCall( tc );
				} );
			}

			return html;
		},

		renderToolCall: function ( tc ) {
			var statusClass = 'success';
			var statusIcon = '&#10003;';
			if ( tc.type === 'confirmation_required' ) {
				statusClass = 'confirmation';
				statusIcon = '&#9888;';
			} else if ( ! tc.success ) {
				statusClass = 'error';
				statusIcon = '&#10007;';
			}

			var inputJson = '';
			var outputJson = '';
			try { inputJson = JSON.stringify( tc.input, null, 2 ); } catch ( e ) { inputJson = String( tc.input ); }
			try { outputJson = JSON.stringify( tc.output, null, 2 ); } catch ( e ) { outputJson = String( tc.output ); }

			return '' +
				'<div class="sentinel-tool-call">' +
					'<div class="sentinel-tool-call-header">' +
						'<span class="sentinel-tool-call-status ' + statusClass + '">' + statusIcon + '</span>' +
						'<span class="sentinel-tool-call-name">' + esc( tc.tool ) + '</span>' +
						'<span class="sentinel-tool-call-chevron">&#9654;</span>' +
					'</div>' +
					'<div class="sentinel-tool-call-body">' +
						'<div><strong>Input:</strong><pre>' + esc( inputJson ) + '</pre></div>' +
						'<div style="margin-top:8px;"><strong>Output:</strong><pre>' + esc( outputJson ) + '</pre></div>' +
					'</div>' +
				'</div>';
		},

		bindToolToggles: function () {
			el.messages.querySelectorAll( '.sentinel-tool-call-header' ).forEach( function ( h ) {
				if ( ! h.dataset.bound ) {
					h.dataset.bound = '1';
					h.addEventListener( 'click', function () {
						h.parentElement.classList.toggle( 'open' );
					} );
				}
			} );
		},

		showTyping: function () {
			if ( ! el.messages ) {
				return;
			}
			var t = document.createElement( 'div' );
			t.className = 'sentinel-typing sentinel-typing-indicator';
			t.innerHTML = '<div class="sentinel-typing-dot"></div><div class="sentinel-typing-dot"></div><div class="sentinel-typing-dot"></div>';
			el.messages.appendChild( t );
			ui.scrollToBottom();
		},

		hideTyping: function () {
			var t = el.messages ? el.messages.querySelector( '.sentinel-typing-indicator' ) : null;
			if ( t ) {
				t.remove();
			}
		},

		scrollToBottom: function () {
			if ( el.messages ) {
				el.messages.scrollTop = el.messages.scrollHeight;
			}
		},

		updateUsage: function ( tokensIn, tokensOut ) {
			if ( el.usage && ( tokensIn || tokensOut ) ) {
				el.usage.textContent = ( tokensIn || 0 ) + ' / ' + ( tokensOut || 0 ) + ' ' + esc( i18n.tokens );
			}
		},

		openSidebar: function () {
			if ( el.sidebar ) {
				el.sidebar.classList.add( 'open' );
			}
			state.sidebarOpen = true;
		},

		closeSidebar: function () {
			if ( el.sidebar ) {
				el.sidebar.classList.remove( 'open' );
			}
			state.sidebarOpen = false;
		},
	};

	// ─── Handlers ────────────────────────────────────────────────

	var handlers = {
		bindAll: function () {
			document.querySelector( '.sentinel-chat-new-btn' ).addEventListener( 'click', function () {
				ui.showWelcome();
				if ( el.welcomeTextarea ) {
					el.welcomeTextarea.focus();
				}
			} );

			document.getElementById( 'sentinel-search-toggle' ).addEventListener( 'click', function () {
				if ( state.searchOpen ) {
					handlers.closeSearch();
				} else {
					handlers.openSearch();
				}
			} );

			document.getElementById( 'sentinel-chats-toggle' ).addEventListener( 'click', function () {
				handlers.closeSearch();
				ui.loadConversations();
			} );

			if ( el.searchInput ) {
				var searchTimeout;
				el.searchInput.addEventListener( 'input', function () {
					clearTimeout( searchTimeout );
					var q = el.searchInput.value;
					searchTimeout = setTimeout( function () {
						if ( q.length >= 2 ) {
							api.searchConversations( q ).then( function ( data ) {
								if ( data.success ) {
									state.conversations = data.conversations || [];
									ui.renderChatList();
								}
							} );
						} else if ( q === '' ) {
							ui.loadConversations();
						}
					}, 300 );
				} );
			}

			el.chatList.addEventListener( 'click', function ( e ) {
				var del = e.target.closest( '.sentinel-chat-item-delete' );
				if ( del ) {
					e.stopPropagation();
					handlers.deleteConversation( parseInt( del.dataset.id ) );
					return;
				}
				var item = e.target.closest( '.sentinel-chat-item' );
				if ( item ) {
					ui.loadConversation( parseInt( item.dataset.id ) );
				}
			} );

			if ( el.welcomeSendBtn ) {
				el.welcomeSendBtn.addEventListener( 'click', handlers.sendFromWelcome );
			}
			if ( el.welcomeTextarea ) {
				el.welcomeTextarea.addEventListener( 'keydown', function ( e ) {
					if ( e.key === 'Enter' && ! e.shiftKey ) {
						e.preventDefault();
						handlers.sendFromWelcome();
					}
				} );
				el.welcomeTextarea.addEventListener( 'input', function () {
					autoResize( el.welcomeTextarea );
				} );
			}

			document.querySelectorAll( '.sentinel-chat-suggestion' ).forEach( function ( btn ) {
				btn.addEventListener( 'click', function () {
					if ( el.welcomeTextarea ) {
						el.welcomeTextarea.value = btn.textContent;
					}
					handlers.sendFromWelcome();
				} );
			} );

			if ( el.chatSendBtn ) {
				el.chatSendBtn.addEventListener( 'click', handlers.sendFromChat );
			}
			if ( el.chatTextarea ) {
				el.chatTextarea.addEventListener( 'keydown', function ( e ) {
					if ( e.key === 'Enter' && ! e.shiftKey ) {
						e.preventDefault();
						handlers.sendFromChat();
					}
				} );
				el.chatTextarea.addEventListener( 'input', function () {
					autoResize( el.chatTextarea );
				} );
			}

			handlers.bindPicker( el.welcomePicker );
			handlers.bindPicker( el.chatPicker );

			document.addEventListener( 'click', function ( e ) {
				document.querySelectorAll( '.sentinel-model-picker.open' ).forEach( function ( p ) {
					if ( ! p.contains( e.target ) ) {
						p.classList.remove( 'open' );
					}
				} );
			} );

			var toggleBtn = document.querySelector( '.sentinel-chat-toggle-sidebar' );
			if ( toggleBtn ) {
				toggleBtn.addEventListener( 'click', function () {
					state.sidebarOpen ? ui.closeSidebar() : ui.openSidebar();
				} );
			}

			var overlay = document.querySelector( '.sentinel-chat-sidebar-overlay' );
			if ( overlay ) {
				overlay.addEventListener( 'click', ui.closeSidebar );
			}

			if ( el.footer ) {
				el.footer.addEventListener( 'click', function ( e ) {
					if ( e.target.closest( '.sentinel-chat-popup-item' ) ) {
						return;
					}
					el.footer.classList.toggle( 'open' );
				} );

				document.addEventListener( 'click', function ( e ) {
					if ( ! el.footer.contains( e.target ) ) {
						el.footer.classList.remove( 'open' );
					}
				} );
			}

			document.addEventListener( 'keydown', function ( e ) {
				if ( e.key === 'Escape' ) {
					if ( state.sidebarOpen ) {
						ui.closeSidebar();
					} else if ( state.activeConversationId ) {
						ui.showWelcome();
					}
				}
				if ( ( e.ctrlKey || e.metaKey ) && e.key === 'n' ) {
					e.preventDefault();
					ui.showWelcome();
					if ( el.welcomeTextarea ) {
						el.welcomeTextarea.focus();
					}
				}
			} );
		},

		bindPicker: function ( pickerEl ) {
			if ( ! pickerEl ) {
				return;
			}
			var btn = pickerEl.querySelector( '.sentinel-model-picker-btn' );
			if ( btn ) {
				btn.addEventListener( 'click', function ( e ) {
					e.stopPropagation();
					document.querySelectorAll( '.sentinel-model-picker.open' ).forEach( function ( p ) {
						if ( p !== pickerEl ) {
							p.classList.remove( 'open' );
						}
					} );
					pickerEl.classList.toggle( 'open' );
				} );
			}
			pickerEl.querySelectorAll( '.sentinel-model-picker-item' ).forEach( function ( item ) {
				item.addEventListener( 'click', function () {
					var provider = item.dataset.provider;
					var model    = item.dataset.model;
					state.selectedProvider = provider;
					state.selectedModel    = model;

					document.querySelectorAll( '.sentinel-model-picker-label' ).forEach( function ( lbl ) {
						lbl.textContent = item.textContent;
					} );

					document.querySelectorAll( '.sentinel-model-picker-item' ).forEach( function ( it ) {
						it.classList.remove( 'active' );
					} );
					document.querySelectorAll( '.sentinel-model-picker-item[data-provider="' + provider + '"][data-model="' + model + '"]' ).forEach( function ( it ) {
						it.classList.add( 'active' );
					} );

					pickerEl.classList.remove( 'open' );

					if ( state.activeConversationId ) {
						api.switchProvider( state.activeConversationId, provider, model );
					}
				} );
			} );
		},

		openSearch: function () {
			state.searchOpen = true;
			if ( el.searchBox ) {
				el.searchBox.classList.remove( 'hidden' );
			}
			document.getElementById( 'sentinel-search-toggle' ).classList.add( 'active' );
			document.getElementById( 'sentinel-chats-toggle' ).classList.remove( 'active' );
			if ( el.searchInput ) {
				el.searchInput.focus();
			}
		},

		closeSearch: function () {
			state.searchOpen = false;
			if ( el.searchBox ) {
				el.searchBox.classList.add( 'hidden' );
			}
			if ( el.searchInput ) {
				el.searchInput.value = '';
			}
			document.getElementById( 'sentinel-search-toggle' ).classList.remove( 'active' );
			document.getElementById( 'sentinel-chats-toggle' ).classList.add( 'active' );
		},

		sendFromWelcome: function () {
			if ( state.isSending ) {
				return;
			}
			var message = el.welcomeTextarea ? el.welcomeTextarea.value.trim() : '';
			if ( ! message ) {
				return;
			}

			state.isSending = true;
			el.welcomeTextarea.value = '';
			autoResize( el.welcomeTextarea );

			api.createConversation( state.selectedProvider, state.selectedModel ).then( function ( data ) {
				if ( data.success ) {
					ui.showChat( data.conversation.id );
					handlers.doSend( parseInt( data.conversation.id ), message );
				} else {
					state.isSending = false;
					alert( data.error || i18n.errorGeneric );
				}
			} ).catch( function () {
				state.isSending = false;
			} );
		},

		sendFromChat: function () {
			if ( state.isSending || ! state.activeConversationId ) {
				return;
			}
			var message = el.chatTextarea ? el.chatTextarea.value.trim() : '';
			if ( ! message ) {
				return;
			}

			state.isSending = true;
			el.chatTextarea.value = '';
			autoResize( el.chatTextarea );

			handlers.doSend( state.activeConversationId, message );
		},

		doSend: function ( conversationId, message ) {
			if ( el.messages ) {
				el.messages.insertAdjacentHTML( 'beforeend', '<div class="sentinel-msg-user">' + esc( message ) + '</div>' );
			}
			ui.showTyping();

			if ( el.chatSendBtn ) {
				el.chatSendBtn.disabled = true;
			}

			api.sendMessage( conversationId, message ).then( function ( data ) {
				ui.hideTyping();
				state.isSending = false;
				if ( el.chatSendBtn ) {
					el.chatSendBtn.disabled = false;
				}

				if ( data.success ) {
					state.messages.push( data.message );

					if ( el.messages ) {
						el.messages.insertAdjacentHTML( 'beforeend', ui.renderAssistantMessage( data.message ) );
						md.highlightAll( el.messages );
						ui.bindToolToggles();
					}
					ui.scrollToBottom();
					ui.updateUsage( data.message.tokens_in, data.message.tokens_out );

					if ( data.conversation ) {
						var found = false;
						state.conversations.forEach( function ( c, idx ) {
							if ( parseInt( c.id ) === conversationId ) {
								state.conversations[ idx ].title = data.conversation.title;
								state.conversations[ idx ].updated_at = data.conversation.updated_at;
								found = true;
							}
						} );
						if ( ! found ) {
							state.conversations.unshift( data.conversation );
						}
						ui.renderChatList();
					}
				} else {
					if ( el.messages ) {
						el.messages.insertAdjacentHTML( 'beforeend',
							'<div class="sentinel-msg-error"><span class="dashicons dashicons-warning"></span> ' + esc( data.error || i18n.errorGeneric ) + '</div>'
						);
					}
					ui.scrollToBottom();
				}

				if ( el.chatTextarea ) {
					el.chatTextarea.focus();
				}
			} ).catch( function () {
				ui.hideTyping();
				state.isSending = false;
				if ( el.chatSendBtn ) {
					el.chatSendBtn.disabled = false;
				}
				if ( el.messages ) {
					el.messages.insertAdjacentHTML( 'beforeend',
						'<div class="sentinel-msg-error"><span class="dashicons dashicons-warning"></span> ' + esc( i18n.errorGeneric ) + '</div>'
					);
				}
				ui.scrollToBottom();
			} );
		},

		deleteConversation: function ( id ) {
			if ( ! confirm( i18n.deleteConfirm ) ) {
				return;
			}
			api.deleteConversation( id ).then( function ( data ) {
				if ( data.success ) {
					state.conversations = state.conversations.filter( function ( c ) {
						return parseInt( c.id ) !== id;
					} );
					ui.renderChatList();
					if ( state.activeConversationId === id ) {
						ui.showWelcome();
					}
				}
			} );
		},
	};

	// ─── Init ────────────────────────────────────��───────────────

	document.addEventListener( 'DOMContentLoaded', ui.init );

} )();
