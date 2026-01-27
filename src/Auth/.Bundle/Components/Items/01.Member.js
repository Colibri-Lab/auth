/**
 * Member view
 * @class
 * @extends Colibri.UI.FlexBox
 * @memberof App.Modules.Auth.Components.Items
 */
App.Modules.Auth.Components.Items.Member = class extends Colibri.UI.FlexBox {
    
    /**
     * @constructor
     * @param {string} name name of component
     * @param {Element|Colibri.UI.component} container container of component
     */
    constructor(name, container) {
        /* создаем компонент и передаем шаблон */
        super(name, container, Colibri.UI.Templates['App.Modules.Auth.Components.Items.Member']);
        this.AddClass('app-modules-auth-components-items-member');

        this._email = this.Children('email');
        this._ttl = this.Children('ttl');
        
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
        this._email.value = this._value.email;
        this._ttl.value = this._value.fio;
    }

}