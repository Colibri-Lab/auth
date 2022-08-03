App.Modules.Auth.Components.ConfirmationForm = class extends Colibri.UI.Component  {
    
    constructor(name, container) {
        /* создаем компонент и передаем шаблон */
        super(name, container, Colibri.UI.Templates['App.Modules.Auth.Components.ConfirmationForm']);
 
        this.AddClass('app-auth-form-component'); 
        this.AddClass('app-auth-confirmation-form-component'); 
        this._property = 'none';

        this._form = this.Children('form-container/form');
        this._validator = new Colibri.UI.FormValidator(this._form);

        this._validator.AddHandler('Validated', (event, args) => {
            if(this._validator.Validate(true, false) && !this._confirming) {
                this._confirming = true;
                this.__confirmationFormConfirmationButtonClicked(event, args);
            }
        });

    } 

    _registerEvents() {
        this.RegisterEvent('PropertyConfirmed', true, 'Сойвство подтверждено');
    }

    set property(value) {
        this._property = value;
    }

    get property() {
        return this._property;
    }

    set shown(value) {
        super.shown = value;
        this._form.Children('code').Focus();
    }

    set value(value) {
        this._form.value = value;
    }

    get value() {
        return this._form.value;
    }

    __confirmationFormConfirmationButtonClicked(event, args) {

        if(this._form.value.code) {

            Auth.Members.ConfirmProperty(this._form.value.code, this._property).then((session) => {
                this._confirming = false;
                this.Dispatch('PropertyConfirmed', {property: this._property});
            }).catch(response => {
                response.result = JSON.parse(response.result);
                if(response.result.validation && Object.keys(response.result.validation).length > 0) {
                    Object.forEach(response.result.validation, (field, message, index) => {
                        this._validator.Invalidate(field, message);
                        if(index === 0) {
                            this._form.FindField(field).Focus();
                        }
                    });
                }
                else {
                    this._validator.Invalidate('code', response.result.message);
                    this._form.Children('code').Focus();
                }
            });    

        }

    }

    Show(property) {
        if(property) {
            this._property = property;
        }
        Auth.Members.BeginConfirmationProcess(this._property).then((session) => {
            super.Show();
        }).catch(response => {
            response.result = JSON.parse(response.result);
            if(response.result.validation && Object.keys(response.result.validation).length > 0) {
                Object.forEach(response.result.validation, (field, message, index) => {
                    this._validator.Invalidate(field, message);
                    if(index === 0) {
                        this._form.FindField(field).Focus();
                    }
                });
            }
            else {
                this._validator.Invalidate('email', response.result.message);
                this._form.Children('email').Focus();
            }
        }); 

    }

}