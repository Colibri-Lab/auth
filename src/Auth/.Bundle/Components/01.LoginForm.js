App.Modules.Auth.Components.LoginForm = class extends Colibri.UI.Component  {
    
    constructor(name, container) {
        /* создаем компонент и передаем шаблон */
        super(name, container, Colibri.UI.Templates['App.Modules.Auth.Components.LoginForm']);
 
        this.AddClass('app-auth-login-form-component'); 

        this._form = this.Children('form-container/form');
        this._validator = new Colibri.UI.FormValidator(this._form);

        this._loginButton = this.Children('button-container/login');
        this._registerButton = this.Children('button-container/register');
        this._resetButton = this.Children('links-container/reset');

        this._validator.AddHandler('Validated', (event, args) => {
            this._loginButton.enabled = this._validator.Validate(true, false);
        });
        
        this._registerButton.AddHandler('Clicked', (event, args) => this.Dispatch('RegisterButtonClicked', args));
        this._resetButton.AddHandler('Clicked', (event, args) => this.Dispatch('ResetButtonClicked', args));
        this._loginButton.AddHandler('Clicked', (event, args) => this.__loginFormLoginButtonClicked(event, args));

    } 

    _registerEvents() {
        this.RegisterEvent('RegisterButtonClicked', true, 'Когда нажата кнопка регистрации');
        this.RegisterEvent('ResetButtonClicked', true, 'Когда нажата кнопка восстановления пароля');
    }

    set shown(value) {
        super.shown = value;
        this._form.Focus();
    }

    __loginFormLoginButtonClicked(event, args) {

        Auth.Session.Login(this._form.value.login, this._form.value.password).then((session) => {
            console.log(session);
        }).catch(response => {
            response.result = JSON.parse(response.result);
            this._validator.Invalidate('login', response.result.message);
            this._form.Children('login').Focus();
            console.log(response);
        });

    }

}