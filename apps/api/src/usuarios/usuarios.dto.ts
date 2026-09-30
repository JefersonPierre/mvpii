import { ApiProperty, ApiPropertyOptional } from '@nestjs/swagger';
import { IsBoolean, IsEmail, IsIn, IsNotEmpty, IsOptional, IsString, MaxLength } from 'class-validator';

export class CriarUsuarioDto {
  @ApiProperty({ example: 'Maria Souza' })
  @IsString()
  @IsNotEmpty({ message: 'Informe o nome.' })
  @MaxLength(120)
  nome: string;

  @ApiProperty({ example: 'maria@laboratorio.local' })
  @IsEmail({}, { message: 'Informe um e-mail válido.' })
  @MaxLength(150)
  email: string;
}

export class EditarUsuarioDto {
  @ApiPropertyOptional()
  @IsOptional()
  @IsString()
  @IsNotEmpty({ message: 'Informe o nome.' })
  @MaxLength(120)
  nome?: string;

  @ApiPropertyOptional()
  @IsOptional()
  @IsEmail({}, { message: 'Informe um e-mail válido.' })
  @MaxLength(150)
  email?: string;
}

export class SituacaoUsuarioDto {
  @ApiProperty()
  @IsBoolean()
  ativo: boolean;
}

export class FiltroUsuariosDto {
  @ApiPropertyOptional({ description: 'Parte do nome ou do e-mail' })
  @IsOptional()
  @IsString()
  busca?: string;

  @ApiPropertyOptional({ enum: ['ativos', 'inativos', 'todos'], default: 'ativos' })
  @IsOptional()
  @IsIn(['ativos', 'inativos', 'todos'])
  situacao?: 'ativos' | 'inativos' | 'todos';
}
