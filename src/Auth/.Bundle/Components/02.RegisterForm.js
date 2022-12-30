App.Modules.Auth.Components.RegisterForm = class extends Colibri.UI.Component  {
    
    constructor(name, container) {
        /* создаем компонент и передаем шаблон */
        super(name, container, Colibri.UI.Templates['App.Modules.Auth.Components.RegisterForm']);
 
        this.AddClass('app-auth-form-component'); 
        this.AddClass('app-auth-register-form-component'); 

        this._form = this.Children('form-container/form');
        this._validator = new Colibri.UI.SimpleFormValidator(this._form);

        this._loginButton = this.Children('button-container2/login');
        this._registerButton = this.Children('button-container/register');

        this._loginButton.AddHandler('Clicked', (event, args) => this.Dispatch('LoginButtonClicked', args));
        this._registerButton.AddHandler('Clicked', (event, args) => this.__registerFormRegisterButtonClicked(event, args));

        this._form.AddHandler('Changed', (event, args) => {
            this.Dispatch('ExternalValidation', args);
            // this._registerButton.enabled = this._validator.Status();
        });

    } 

    _registerEvents() {
        this.RegisterEvent('LoginButtonClicked', true, 'Когда нажата кнопка входа');
        this.RegisterEvent('ExternalValidation', true, 'Когда требуется валидация');
    }

    set shown(value) {
        super.shown = value;
        this._form.Focus();
    }

    __registerFormRegisterButtonClicked(event, args) {

        if(!this._validator.ValidateAll()) {

            const component = this._form.container.querySelector('.app-validate-error').tag('component');
            if(component) {
                component.Focus();
            }

            return;
        }
 
        const formData = this._form.value;
        Auth.Members.Register(
            formData.email, formData.phone.replaceAll(/[^[0-9+]/, ''), formData.pass.password, formData.pass.confirmation, formData.fio.first_name, formData.fio.last_name, formData.fio.patronymic, formData.gender, formData.birthdate
        ).then((session) => {

        }).catch(response => {
            response.result = (typeof response.result === 'string' ? JSON.parse(response.result) : response.result);
            if(response.result.validation && Object.keys(response.result.validation).length > 0) {
                Object.forEach(response.result.validation, (field, message, index) => {
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

}