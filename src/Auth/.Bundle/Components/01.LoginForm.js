App.Modules.Auth.Components.LoginForm = class extends Colibri.UI.Component  {
    
    constructor(name, container) {
        /* создаем компонент и передаем шаблон */
        super(name, container, Colibri.UI.Templates['App.Modules.Auth.Components.LoginForm']);
 
        this.AddClass('app-auth-form-component'); 
        this.AddClass('app-auth-login-form-component'); 

        this._form = this.Children('form-container/form');
        this._validator = new Colibri.UI.SimpleFormValidator(this._form); 

        this._loginButton = this.Children('button-container/login');
        this._registerButton = this.Children('button-container2/register');
        this._resetButton = this.Children('links-container/reset');

        this._form.AddHandler('Changed', (event, args) => {
            this._loginButton.enabled = this._validator.Status();
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

        if(!this._validator.ValidateAll()) {
            return;
        }

        const value = Object.cloneRecursive(this._form.value);
        Auth.Session.Login(value.login, value.password, value.code ?? null).then((session) => {

            if(!session) { // 2-х факторка
                const fields = Object.cloneRecursive(this._form.fields);
                fields.login.params.readonly = true;
                fields.password.params.readonly = true;
                fields.code.params.hidden = false;
                this._form.fields = fields;
                this._form.value = value;
                this._form.Children('code').Focus();
            } 

        }).catch(response => {
            response.result = JSON.parse(response.result);
            this._validator.Invalidate('form', response.result.message);
            this._form.Focus();
        });

    }

}