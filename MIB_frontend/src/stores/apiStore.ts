// import { defineStore } from 'pinia';
// import instance from '@/assets/axios';


// export const useApiService = defineStore('apiService', {
//     actions:{
        
//         async encriptPost(apiUrl:string,params:any)
//         {
            
//             let encrypted = await this.encryptedData(params);
//             let response = await instance.post(`${apiUrl}`,{
//                 payload:encrypted
//             });

//             return response.data;
//         },

       
//     }
// });