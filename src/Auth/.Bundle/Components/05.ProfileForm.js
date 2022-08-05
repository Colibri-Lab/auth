App.Modules.Auth.Components.ProfileForm = class extends Colibri.UI.Component  {
    
    constructor(name, container) {
        /* создаем компонент и передаем шаблон */
        super(name, container, Colibri.UI.Templates['App.Modules.Auth.Components.ProfileForm']);
 
        this.AddClass('app-auth-form-component'); 
        this.AddClass('app-auth-profile-form-component'); 

        this._form = this.Children('form-container/form');
        this._validator = new Colibri.UI.FormValidator(this._form);

        this._saveButton = this.Children('button-container/save');
        this._validator.AddHandler('Validated', (event, args) => {
            this._saveButton.enabled = this._validator.Validate(true, false);
        });
        
        this._saveButton.AddHandler('Clicked', (event, args) => this.__profileFormSaveButtonClicked(event, args));

    } 

    set shown(value) {
        super.shown = value;
        this._form.Focus();
    }
    
    set value(value) {
        this._form.value = value;
    }

    get value() {
        return this._form.value;
    }

    __profileFormSaveButtonClicked(event, args) {

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