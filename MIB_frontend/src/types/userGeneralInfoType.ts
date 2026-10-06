export interface userGeneralInfoType{
id?: number | string,
 first_name: string
 last_name: string
 gender: number | string,
 birth_date: string,
 profile_image:string,
 cover_image:string,
 school:string,
 total_points?:number
 rank?:number
 posts?:[],
 visibility:{
      [key: string]: string
 },
 profession:{
      company:string,
      location:string,
      profession:string
 },
 slug:string,
 profile_url?:string
}