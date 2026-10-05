export interface workExperianceType{
    id?:number,
    title:string,
    company:string,
    currently_working:number,
    location:string,
    selectEmpType:number,
    locationType:number,
    startingDate:Date | null,
    position:string,
    endDate:Date | null,
    visibility:{
      [key: string]: number
    }
}