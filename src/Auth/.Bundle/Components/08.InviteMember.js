/**
 * Invite member
 * @class
 * @extends Colibri.UI.Component
 * @memberof App.Modules.Auth.Components
 */
App.Modules.Auth.Components.InviteMember = class extends Colibri.UI.Component {
    
    /**
     * @constructor
     * @param {string} name name of component
     * @param {Element|Colibri.UI.component} container container of component
     */
    constructor(name, container) {
        /* создаем компонент и передаем шаблон */
        super(name, container, Colibri.UI.Templates['App.Modules.Auth.Components.InviteMember']);
        this.AddClass('app-modules-auth-components-invitemember');
        this.AddClass('app-auth-form-component'); 

        this._form = this.Children('form-container/form'); 
        this._validator = new Colibri.UI.SimpleFormValidator(this._form);

        this._saveButton = this.Children('button-container/save');
        this._saveButton.AddHandler('Clicked', (event, args) => this.__saveButtonClicked(event, args)); 

    }

    __saveButtonClicked(event, args) {

        if(!this._validator.ValidateAll()) {

            const component = this._form.container.querySelector('.app-validate-error').tag('component');
            if(component) {
                component.Focus();
            }

            return;
        }

        Auth.Members.Invite(this._form.value.email, this._form.value.phone, this._form.value.params).then(result => {
            this.Dispatch('Completed', result);
            this.Hide();
        }).catch(response => {
            response.result = (typeof response.result === 'string' ? JSON.parse(response.result) : response.result);
            if(response.result.validation && Object.keys(response.result.validation).length > 0) {
                Object.forEach(response.result.validation, (field, message, index) => {
                    if(['password', 'confirmation'].indexOf(field) !== -1) {
                        field = 'pass';
                    }
                    this._validator.Invalidate(field, message);
                    if(index === 0) {
                        this._form.FindField(field).Focus();
                    }
                });
            }
            else {
                this._validator.Invalidate('form', response.result.message);
                this._form.Focus();
            }
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
        
        this._form.value = this._value;

    }

}