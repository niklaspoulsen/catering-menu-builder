(function () {
	'use strict';

	var EXTENSION_NAMESPACE = 'catering-menu-builder';
	var FILTER_NAMESPACE = 'catering-menu-builder/blocks-integration';

	function getBlocksCheckout() {
		if (
			window.wc &&
			window.wc.blocksCheckout &&
			typeof window.wc.blocksCheckout.registerCheckoutFilters === 'function'
		) {
			return window.wc.blocksCheckout;
		}

		return null;
	}

	function getElementApi() {
		if (window.wp && window.wp.element && typeof window.wp.element.createElement === 'function') {
			return window.wp.element;
		}

		return null;
	}

	function getHtmlEntitiesApi() {
		if (window.wp && window.wp.htmlEntities && typeof window.wp.htmlEntities.decodeEntities === 'function') {
			return window.wp.htmlEntities;
		}

		return null;
	}

	function decode(value) {
		var htmlEntities = getHtmlEntitiesApi();

		value = value === null || typeof value === 'undefined' ? '' : String(value);

		if (htmlEntities) {
			return htmlEntities.decodeEntities(value);
		}

		return value;
	}

	function getExtensionData(extensions, args) {
		if (extensions && extensions[EXTENSION_NAMESPACE]) {
			return extensions[EXTENSION_NAMESPACE];
		}

		if (
			args &&
			args.cartItem &&
			args.cartItem.extensions &&
			args.cartItem.extensions[EXTENSION_NAMESPACE]
		) {
			return args.cartItem.extensions[EXTENSION_NAMESPACE];
		}

		return null;
	}

	function isObject(value) {
		return value !== null && typeof value === 'object' && !Array.isArray(value);
	}

	function isNonEmptyArray(value) {
		return Array.isArray(value) && value.length > 0;
	}

	function addClassName(currentClassName, classNameToAdd) {
		var classes = [];

		if (currentClassName) {
			classes = String(currentClassName).split(/\s+/);
		}

		if (classes.indexOf(classNameToAdd) === -1) {
			classes.push(classNameToAdd);
		}

		return classes.join(' ').trim();
	}

	function injectStyles() {
		if (document.getElementById('cmbwc-blocks-integration-styles')) {
			return;
		}

		var style = document.createElement('style');
		style.id = 'cmbwc-blocks-integration-styles';
		style.textContent = [
			'.cmbwc-block-cart-meta{margin-top:8px;font-size:13px;line-height:1.45;color:inherit;}',
			'.cmbwc-block-cart-row{margin-top:5px;}',
			'.cmbwc-block-cart-label{font-weight:600;margin-right:4px;}',
			'.cmbwc-block-cart-list{margin:4px 0 0 0;padding-left:18px;}',
			'.cmbwc-block-cart-list li{margin:0 0 2px 0;}',
			'.wc-block-cart-items__row.cmbwc-block-locked-line .wc-block-components-quantity-selector,',
			'.wc-block-cart-items__row.cmbwc-block-service-line .wc-block-components-quantity-selector,',
			'.wc-block-components-order-summary-item.cmbwc-block-locked-line .wc-block-components-quantity-selector,',
			'.wc-block-components-order-summary-item.cmbwc-block-service-line .wc-block-components-quantity-selector{display:none!important;}',
			'.wc-block-cart-items__row.cmbwc-block-locked-line .wc-block-cart-item__quantity,',
			'.wc-block-cart-items__row.cmbwc-block-service-line .wc-block-cart-item__quantity{pointer-events:none;}',
			'.cmbwc-block-locked-qty{display:inline-block;font-size:13px;font-weight:600;}'
		].join('\n');

		document.head.appendChild(style);
	}

	function buildList(items, type) {
		var element = getElementApi();

		if (!element || !isNonEmptyArray(items)) {
			return null;
		}

		var children = items.map(function (item, index) {
			var label = '';

			if (typeof item === 'string') {
				label = item;
			} else if (isObject(item)) {
				if (type === 'qty-name') {
					label = String(item.qty && Number(item.qty) > 1 ? item.qty + ' x ' : '') + String(item.name || '');
				} else {
					label = String(item.name || '');
				}
			}

			label = decode(label);

			if (!label) {
				return null;
			}

			return element.createElement('li', { key: index }, label);
		}).filter(Boolean);

		if (!children.length) {
			return null;
		}

		return element.createElement(
			'ul',
			{
				className: 'cmbwc-block-cart-list'
			},
			children
		);
	}

	function buildRow(label, value, key) {
		var element = getElementApi();

		if (!element || value === null || typeof value === 'undefined' || value === '') {
			return null;
		}

		return element.createElement(
			'div',
			{
				className: 'cmbwc-block-cart-row',
				key: key
			},
			element.createElement(
				'span',
				{
					className: 'cmbwc-block-cart-label'
				},
				label
			),
			element.createElement(
				'span',
				{
					className: 'cmbwc-block-cart-value'
				},
				value
			)
		);
	}

	function buildMenuMeta(data) {
		var element = getElementApi();

		if (!element || !data || !data.is_menu) {
			return null;
		}

		var rows = [];

		if (data.covers && Number(data.covers) > 0) {
			rows.push(buildRow('Kuverter:', String(data.covers), 'covers'));
		}

		if (isNonEmptyArray(data.included)) {
			rows.push(buildRow('Indhold:', buildList(data.included, 'string'), 'included'));
		}

		if (isNonEmptyArray(data.addons)) {
			rows.push(buildRow('Valgte tilvalg:', buildList(data.addons, 'qty-name'), 'addons'));
		}

		if (isNonEmptyArray(data.services)) {
			rows.push(buildRow('Valgt service:', buildList(data.services, 'qty-name'), 'services'));
		}

		rows = rows.filter(Boolean);

		if (!rows.length) {
			return null;
		}

		return element.createElement(
			'div',
			{
				className: 'cmbwc-block-cart-meta'
			},
			rows
		);
	}

	function buildChildMeta(data) {
		var element = getElementApi();

		if (!element || !data || !data.is_child) {
			return null;
		}

		var rows = [];

		if (data.parent_name) {
			rows.push(buildRow('Tilhører:', decode(data.parent_name), 'parent'));
		}

		if (data.is_child_service && data.is_locked && data.locked_qty && Number(data.locked_qty) > 0) {
			rows.push(
				element.createElement(
					'div',
					{
						className: 'cmbwc-block-cart-row',
						key: 'locked-qty'
					},
					element.createElement(
						'span',
						{
							className: 'cmbwc-block-locked-qty'
						},
						'Antal: ' + String(data.locked_qty)
					)
				)
			);
		}

		rows = rows.filter(Boolean);

		if (!rows.length) {
			return null;
		}

		return element.createElement(
			'div',
			{
				className: 'cmbwc-block-cart-meta cmbwc-block-child-meta'
			},
			rows
		);
	}

	function renderItemName(defaultValue, extensions, args) {
		var element = getElementApi();
		var data = getExtensionData(extensions, args);

		if (!element || !data) {
			return defaultValue;
		}

		if (!data.is_menu && !data.is_child) {
			return defaultValue;
		}

		var meta = data.is_menu ? buildMenuMeta(data) : buildChildMeta(data);

		if (!meta) {
			return defaultValue;
		}

		return element.createElement(
			'div',
			{
				className: 'cmbwc-block-item-name-wrap'
			},
			element.createElement(
				'div',
				{
					className: 'cmbwc-block-item-name'
				},
				defaultValue
			),
			meta
		);
	}

	function renderCartItemClass(defaultValue, extensions, args) {
		var data = getExtensionData(extensions, args);
		var className = defaultValue || '';

		if (!data) {
			return className;
		}

		if (data.is_menu) {
			className = addClassName(className, 'cmbwc-block-menu-line');
		}

		if (data.is_child) {
			className = addClassName(className, 'cmbwc-block-child-line');
		}

		if (data.is_child_service) {
			className = addClassName(className, 'cmbwc-block-service-line');
		}

		if (data.is_locked) {
			className = addClassName(className, 'cmbwc-block-locked-line');
		}

		if (data.is_deposit) {
			className = addClassName(className, 'cmbwc-block-deposit-line');
		}

		return className;
	}

	function renderShowRemoveItemLink(defaultValue, extensions, args) {
		var data = getExtensionData(extensions, args);

		if (!data) {
			return defaultValue;
		}

		if (data.is_child_service || data.is_locked) {
			return false;
		}

		return defaultValue;
	}

	function registerFilters() {
		var blocksCheckout = getBlocksCheckout();

		if (!blocksCheckout) {
			return;
		}

		injectStyles();

		blocksCheckout.registerCheckoutFilters(FILTER_NAMESPACE, {
			itemName: renderItemName,
			cartItemClass: renderCartItemClass,
			showRemoveItemLink: renderShowRemoveItemLink
		});
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', registerFilters);
	} else {
		registerFilters();
	}
})();
