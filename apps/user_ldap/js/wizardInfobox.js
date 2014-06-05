/* global LdapWizard */

(function() {
	var WizardInfobox = function($el) {
		this.$el = $el;
		this.entries = {};
	}

	WizardInfobox.prototype = {
		/**
		 * shows the note with the given ID in the infobox
		 * @param {string} id
		 * @param {string} text
		 */
		show: function(id, text) {
			$p = $('<span></span>');
			$p.text(text);
			this.$el.append($p);
			this.entries[id] = $p;
			this.autoVisibility();
		},

		/**
		 * hides the infobox
		 */
		hide: function() {
			for (id in this.entries) {
				this.drop(id);
			}
			//drop() will set visibilty when no note is left
		},

		/**
		 * returns whether any notes are shown or not
		 * @returns {boolean}
		 */
		isEmpty: function() {
			for(id in this.entries) {
				return false;
			}
			return true;
		},

		/**
		 * returns whether the infobox is visible
		 * @returns {boolean}
		 *
		 */
		isVisible: function() {
			return !this.$el.hasClass('invisible');
		},

		/**
		 * decides to hide or show the infobox
		 */
		autoVisibility: function() {
			if($this.isEmpty()) {
				this.setVisibility(false);
			} else {
				this.setVisibility(true);
			}
		}

		/**
		 * makes the infbox visible or invisible
		 * @param {boolean} visible
		 */
		setVisibility: function(visible) {
			if(visible === true && !this.isVisible()) {
				this.$el.removeClass('invisible');
			} else if(visible === false && this.isVisible()) {
				this.$el.addClass('invisible');
			}
		},

		/**
		 * removes a specified note from the infobox
		 * @param {string} id
		 */
		drop: function(id) {
			if(typeof this.entries[id] !== "undefined") {
				this.entries[id].remove();
				delete this.entries[id];
			}

			this.autoVisibility();
		},
	};

	OCA.LDAP.Wizard.Infobox = WizardInfobox;
})();
