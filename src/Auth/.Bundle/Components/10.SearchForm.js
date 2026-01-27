/**
 * Searches for contacts
 * @class
 * @extends Colibri.UI.Pane
 * @memberof App.Modules.Auth.Components
 */
App.Modules.Auth.Components.SearchForm = class extends Colibri.UI.Component {
    
    /**
     * @constructor
     * @param {string} name name of component
     * @param {Element|Colibri.UI.component} container container of component
     */
    constructor(name, container) {
        /* создаем компонент и передаем шаблон */
        super(name, container, Colibri.UI.Templates['App.Modules.Auth.Components.SearchForm']);
        this.AddClass('app-modules-auth-components-searchform');
        this.AddClass('app-auth-form-component'); 
        
        this._term = this.Children('term');
        this._list = this.Children('list');
        this._listGroup = this.Children('list/group');
        

        this._term.AddHandler(['Filled','Cleared'], this.__termFilled, false, this);
        this._list.AddHandler('ItemClicked', this.__listItemClicked, false, this);

    }

    __listItemClicked(event, args) {
        this.Dispatch('Completed', args.item.value);
    }

    __termFilled(event, args) {
        Auth.Members.Search(this._term.value).then((result) => {
            this._listGroup.value = Object.values(result.list);
        });
    }

    /** @protected */
    _registerEvents() {
        super._registerEvents();
        this.RegisterEvent('Completed', true, 'When invitation is sent');
    }

    /**
     * Value Object
     * @type {Object}
     */
    get value() {
        return this._value;
    }
    /**
     * Value Object
     * @type {Object}
     */
    set value(value) {
        this._value = value;
        this._showValue();
    }
    _showValue() {
        
    }

}