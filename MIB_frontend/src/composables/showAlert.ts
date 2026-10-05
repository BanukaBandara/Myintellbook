
import Swal, { type SweetAlertIcon } from 'sweetalert2';

const alert = async(Config:object) =>
    {
        

        let userConfig = {
            icon:'success' as SweetAlertIcon,
            title:'',
            showConfirmButton:false,
            showCancelButton:false,
            timer:0,
            confirmButtonText:'',
            text:'',
            confirmButtonColor:''
        }

        userConfig = {...userConfig, ...Config}

        return Swal.fire({
                position: "center",
                icon: userConfig.icon,
                title: userConfig.title,
                showConfirmButton: userConfig.showConfirmButton,
                text: userConfig.text,
                showCancelButton: userConfig.showCancelButton,
                confirmButtonText:userConfig.confirmButtonText,
                heightAuto:false,
                confirmButtonColor:userConfig.confirmButtonColor,
                customClass:{
                    confirmButton:'btn fw-semibold m-1 btn-danger btn-md',
                    cancelButton:'btn border fw-semibold btn-light btn-md'
                },
                timer: userConfig.timer,
                buttonsStyling: false
            });
    }

export default alert;
