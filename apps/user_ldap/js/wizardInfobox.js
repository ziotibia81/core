/* global LdapWizard */

(function() {
	var WizardInfobox = function($el) {
		this.$el = $el;
		this.noteClassPrefix = 'wizardInfoNote-';
		this.noteClassCommon = 'wizardInfoNote';
	}

	WizardInfobox.prototype = {
		/**
		 * shows the note with the given ID in the infobox
		 * @param {string} id
		 * @param {string} text
		 */
		show: function(id, text) {
			var $p = $('<span class="'+this.noteClassCommon+'"></span>');
			$p.text(text);
			$p.addClass(this.noteClassPrefix+id);
			this.$el.append($p);

			this.autoVisibility();
		},

		/**
		 * hides the infobox (and thus removes all notes)
		 */
		hide: function() {
			$('.'+this.noteClassCommon).remove();
			this.autoVisibility();
		},

		/**
		 * returns whether any notes are shown or not
		 * @returns {boolean}
		 */
		isEmpty: function() {
			return ($('.'+this.noteClassCommon).length === 0);
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
			if(this.isEmpty()) {
				this.setVisibility(false);
			} else {
				this.setVisibility(true);
			}
		},

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
			if($('.'+this.noteClassPrefix+id).length > 0) {
				$('.'+this.noteClassPrefix+id).remove();
			}

			this.autoVisibility();
		},
	};

	OCA.LDAP.Wizard.Infobox = WizardInfobox;
})();
