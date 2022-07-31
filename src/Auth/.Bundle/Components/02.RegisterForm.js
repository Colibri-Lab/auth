App.Modules.Auth.Components.RegisterForm = class extends Colibri.UI.Component  {
    
    constructor(name, container) {
        /* создаем компонент и передаем шаблон */
        super(name, container, Colibri.UI.Templates['App.Modules.Auth.Components.RegisterForm']);
 
        this.AddClass('app-auth-login-form-component'); 

        this._form = this.Children('form-container/form');
        this._validator = new Colibri.UI.FormValidator(this._form);

        Auth.Store.AsyncQuery('auth.settings').then((settings) => {
            this._form.fields = settings.forms.register.fields; 
        });

        this._loginButton = this.Children('button-container/login');
        this._registerButton = this.Children('button-container/register');

        this._validator.AddHandler('Validated', (event, args) => {
            this._registerButton.enabled = this._validator.Validate(true, false);
        });
        
        this._loginButton.AddHandler('Clicked', (event, args) => this.Dispatch('LoginButtonClicked', args));

    } 

    _registerEvents() {
        this.RegisterEvent('LoginButtonClicked', true, 'Когда нажата кнопка входа');
    }

    set shown(value) {
        super.shown = value;
        this._form.Focus();
    }

}