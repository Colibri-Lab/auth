App.Modules.Auth.Components.ProfileForm = class extends Colibri.UI.Component  {
    
    constructor(name, container) {
        /* создаем компонент и передаем шаблон */
        super(name, container, Colibri.UI.Templates['App.Modules.Auth.Components.ProfileForm']);
 
        this.AddClass('app-auth-form-component'); 
        this.AddClass('app-auth-profile-form-component'); 

        this._form = this.Children('form-container/form'); 
        this._validator = new Colibri.UI.SimpleFormValidator(this._form);

        this._saveButton = this.Children('button-container/save');
        
        this._saveButton.AddHandler('Clicked', this.__profileFormSaveButtonClicked, false, this);

    } 

    /** @protected */
    _registerEvents() {
        super._registerEvents();
        this.RegisterEvent('Completed', true, 'When profile is saved');
    }

    /**
     * Shows the component
     * @type {boolean}
     */
    set shown(value) {
        super.shown = value;
        this._form.Focus();
    }
    
    /**
     * Value object
     * @type {object}
     */
    set value(value) {
        this._form.value = value;
    }

    get value() {
        return this._form.value;
    }

    /**
     * @private
     * @param {Colibri.Events.Event} event event object
     * @param {*} args event arguments
     */ 
    __profileFormSaveButtonClicked(event, args) {
        
        if(!this._validator.ValidateAll()) {

            const component = this._form.container.querySelector('.app-validate-error').tag('component');
            if(component) {
                component.Focus();
            }

            return;
        }
        Auth.Members.SaveProfile(this._form.value.last_name, this._form.value.first_name, this._form.value.patronymic, this._form.value.birthdate, this._form.value.gender).then(session => {
            this.Dispatch('Completed', session);
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

    /**
     * Simple form, without estended fields
     * @type {boolean}
     */
    get simple() {
        return this._simple;
    }
    /**
     * Simple form, without estended fields
     * @type {boolean}
     */
    set simple(value) {
        this._simple = value;
        this._showSimple();
    }
    _showSimple() {
        if(this._simple) {
            const fields = Object.cloneRecursive(this._form.fields);
            fields.gender.params.hidden = true;
            fields.patronymic.params.hidden = true;
            fields.birthdate.params.hidden = true;
            this._form.fields = fields;
        }
    }

}