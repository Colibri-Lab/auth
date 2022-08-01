App.Modules.Auth.Components.RegisterForm = class extends Colibri.UI.Component  {
    
    constructor(name, container) {
        /* создаем компонент и передаем шаблон */
        super(name, container, Colibri.UI.Templates['App.Modules.Auth.Components.RegisterForm']);
 
        this.AddClass('app-auth-login-form-component'); 

        this._form = this.Children('form-container/form');
        this._validator = new Colibri.UI.FormValidator(this._form);

        this._loginButton = this.Children('button-container/login');
        this._registerButton = this.Children('button-container/register');

        this._loginButton.AddHandler('Clicked', (event, args) => this.Dispatch('LoginButtonClicked', args));
        this._registerButton.AddHandler('Clicked', (event, args) => this.__registerFormRegisterButtonClicked(event, args));

        this._validator.AddHandler('Validated', (event, args) => {
            this._registerButton.enabled = this._validator.Validate(true, false);
        });
        

    } 

    _registerEvents() {
        this.RegisterEvent('LoginButtonClicked', true, 'Когда нажата кнопка входа');
    }

    set shown(value) {
        super.shown = value;
        this._form.Focus();
    }

    __registerFormRegisterButtonClicked(event, args) {
 
        const formData = this._form.value;
        console.log(formData);
        Auth.Members.Register(
            formData.email, formData.phone, formData.password, '', formData.first_name, formData.last_name, formData.patronymic, formData.gender, formData.birthdate
        ).then((session) => {
            console.log(session);
        }).catch(response => {
            response.result = JSON.parse(response.result);
            if(response.result.validation && Object.keys(response.result.validation).length > 0) {
                Object.forEach(response.result.validation, (field, message, index) => {
                    this._validator.Invalidate(field, message);
                    if(index === 0) {
                        this._form.Children(field).Focus();
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