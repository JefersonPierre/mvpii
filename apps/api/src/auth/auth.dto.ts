import { ApiProperty } from '@nestjs/swagger';
import { IsEmail, IsNotEmpty, IsString } from 'class-validator';

export class LoginDto {
  @ApiProperty({ example: 'admin@laboratorio.local' })
  @IsEmail({}, { message: 'Informe um e-mail válido.' })
  email: string;

  @ApiProperty({ example: 'Admin12345' })
  @IsString()
  @IsNotEmpty({ message: 'Informe a senha.' })
  senha: string;
}

export class RecuperarSenhaDto {
  @ApiProperty({ example: 'admin@laboratorio.local' })
  @IsEmail({}, { message: 'Informe um e-mail válido.' })
  email: string;
}

export class RedefinirSenhaDto {
  @ApiProperty()
  @IsString()
  @IsNotEmpty()
  token: string;

  @ApiProperty({ example: 'NovaSenha123' })
  @IsString()
  novaSenha: string;
}
