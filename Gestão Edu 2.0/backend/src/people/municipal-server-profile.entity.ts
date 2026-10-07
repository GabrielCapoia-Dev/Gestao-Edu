import { CreateDateColumn, Entity, JoinColumn, OneToMany, OneToOne, PrimaryColumn } from 'typeorm';
import { Person } from './person.entity';
import { Matricula } from './matricula.entity';

// US003/CT03: perfil municipal não é a pessoa; pode possuir várias matrículas.
@Entity('municipal_server_profiles')
export class MunicipalServerProfile {
  @PrimaryColumn({ name: 'person_uuid', type: 'char', length: 36 }) personUuid!: string;
  @CreateDateColumn({ name: 'created_at', type: 'datetime', precision: 6 }) createdAt!: Date;
  @OneToOne(() => Person, (person) => person.municipalServer, { onDelete: 'RESTRICT' })
  @JoinColumn({ name: 'person_uuid', referencedColumnName: 'uuid' }) person!: Person;
  @OneToMany(() => Matricula, (matricula) => matricula.server) matriculas!: Matricula[];
}
